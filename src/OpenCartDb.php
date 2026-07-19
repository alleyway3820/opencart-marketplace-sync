<?php
/**
 * OpenCart Database Manager
 *
 * Handles all OpenCart MySQL operations for the eBay sync app.
 */
class OpenCartDb
{
    private PDO $db;
    private string $prefix;

    public function __construct(PDO $pdo, string $prefix = "oc_")
    {
        $this->db = $pdo;
        $this->prefix = $prefix;
    }

    /**
     * Find a product by its model/SKU.
     */
    public function getProductBySku(string $sku): ?int
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT product_id FROM {$this->prefix}product WHERE model = ? LIMIT 1"
            );
            $stmt->execute([$sku]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (int)$row["product_id"] : null;
        } catch (PDOException $e) {
            error_log("OpenCartDb::getProductBySku error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Find a product by its eBay listing ID stored in the SKU field.
     */
    public function getProductByListingId(string $listingId): ?int
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT product_id FROM {$this->prefix}product WHERE sku = ? LIMIT 1"
            );
            $stmt->execute([$listingId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (int)$row["product_id"] : null;
        } catch (PDOException $e) {
            error_log("OpenCartDb::getProductByListingId error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Create a new product with description, category, store, and SEO URL.
     *
     * @param array $data Keys: model, sku, quantity, price, image, status, name, description,
     *                    meta_title, category_id
     * @return int|null New product_id or null on failure
     */
    public function createProduct(array $data): ?int
    {
        try {
            $this->db->beginTransaction();
            $now = date("Y-m-d H:i:s");
            $today = date("Y-m-d");

            $stmt = $this->db->prepare(
                "INSERT INTO {$this->prefix}product SET
                    model = ?, sku = ?, quantity = ?, stock_status_id = 6,
                    image = ?, manufacturer_id = ?, shipping = 1, price = ?,
                    points = 0, tax_class_id = 9, rating = 0, date_available = ?,
                    weight = ?, weight_class_id = ?, length = ?, width = ?,
                    height = ?, length_class_id = ?, subtract = 1, minimum = 1,
                    sort_order = 1, status = ?, date_added = ?, date_modified = ?"
            );
            $stmt->execute([
                $data["model"] ?? "",
                $data["sku"] ?? "",
                (int)($data["quantity"] ?? 1),
                $data["image"] ?? "",
                (int)($data["manufacturer_id"] ?? 0),
                $data["price"] ?? "0.0000",
                $today,
                $data["weight"] ?? "0.0000",
                (int)($data["weight_class_id"] ?? 1),
                $data["length"] ?? "0.0000",
                $data["width"] ?? "0.0000",
                $data["height"] ?? "0.0000",
                (int)($data["length_class_id"] ?? 1),
                (int)($data["status"] ?? 1),
                $now,
                $now,
            ]);

            $productId = (int)$this->db->lastInsertId();

            // Product description
            $name = $data["name"] ?? "";
            $metaTitle = $data["meta_title"] ?? $name;
            $stmt = $this->db->prepare(
                "INSERT INTO {$this->prefix}product_description SET
                    product_id = ?, language_id = 1, name = ?, description = ?,
                    tag = '', meta_title = ?, meta_description = '', meta_keyword = ''"
            );
            $stmt->execute([$productId, $name, $data["description"] ?? "", $metaTitle]);

            // Product to category
            $catId = (int)($data["category_id"] ?? 0);
            if ($catId > 0) {
                $stmt = $this->db->prepare(
                    "INSERT INTO {$this->prefix}product_to_category (product_id, category_id)
                     VALUES (?, ?)"
                );
                $stmt->execute([$productId, $catId]);
            }

            // Product to store
            $storeId = (int)($data["store_id"] ?? 0);
            $stmt = $this->db->prepare(
                "INSERT INTO {$this->prefix}product_to_store (product_id, store_id)
                 VALUES (?, ?)"
            );
            $stmt->execute([$productId, $storeId]);

            // SEO URL
            $keyword = $this->nameToUrl($name);
            $stmt = $this->db->prepare(
                "INSERT INTO {$this->prefix}seo_url SET
                    language_id = 1, store_id = ?, `key` = 'product_id', `value` = ?,
                    keyword = ?"
            );
            $stmt->execute([$storeId, (string)$productId, $keyword]);

            $this->db->commit();
            return $productId;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("OpenCartDb::createProduct error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update an existing product and its description.
     */
    public function updateProduct(int $productId, array $data): bool
    {
        try {
            $this->db->beginTransaction();
            $now = date("Y-m-d H:i:s");

            // Build SET clauses dynamically based on provided fields
            $fields = [];
            $params = [];
            foreach (["model", "sku", "quantity", "price", "image", "status"] as $key) {
                if (array_key_exists($key, $data)) {
                    $fields[] = "$key = ?";
                    $params[] = $key === "quantity" || $key === "status"
                        ? (int)$data[$key]
                        : $data[$key];
                }
            }
            if (!empty($fields)) {
                $params[] = $now;
                $params[] = $productId;
                $stmt = $this->db->prepare(
                    "UPDATE {$this->prefix}product SET "
                    . implode(", ", $fields)
                    . ", date_modified = ? WHERE product_id = ?"
                );
                $stmt->execute($params);
            }

            // Update description
            if (isset($data["name"]) || isset($data["description"]) || isset($data["meta_title"])) {
                $descFields = [];
                $descParams = [];
                if (isset($data["name"])) {
                    $descFields[] = "name = ?";
                    $descParams[] = $data["name"];
                }
                if (isset($data["description"])) {
                    $descFields[] = "description = ?";
                    $descParams[] = $data["description"];
                }
                if (isset($data["meta_title"])) {
                    $descFields[] = "meta_title = ?";
                    $descParams[] = $data["meta_title"];
                }
                if (!empty($descFields)) {
                    $descParams[] = $productId;
                    $stmt = $this->db->prepare(
                        "UPDATE {$this->prefix}product_description SET "
                        . implode(", ", $descFields)
                        . " WHERE product_id = ? AND language_id = 1"
                    );
                    $stmt->execute($descParams);
                }
            }

            // Update category link
            if (isset($data["category_id"])) {
                $stmt = $this->db->prepare(
                    "DELETE FROM {$this->prefix}product_to_category
                     WHERE product_id = ?"
                );
                $stmt->execute([$productId]);

                $catId = (int)$data["category_id"];
                if ($catId > 0) {
                    $stmt = $this->db->prepare(
                        "INSERT INTO {$this->prefix}product_to_category (product_id, category_id)
                         VALUES (?, ?)"
                    );
                    $stmt->execute([$productId, $catId]);
                }
            }

            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("OpenCartDb::updateProduct error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add an additional image to a product.
     */
    public function createProductImage(int $productId, string $imagePath, int $sortOrder = 0): bool
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO {$this->prefix}product_image (product_id, image, sort_order)
                 VALUES (?, ?, ?)"
            );
            $stmt->execute([$productId, $imagePath, $sortOrder]);
            return true;
        } catch (PDOException $e) {
            error_log("OpenCartDb::createProductImage error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all categories as [id => name] map.
     */
    public function getCategoryMap(): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT c.category_id, cd.name
                 FROM {$this->prefix}category c
                 JOIN {$this->prefix}category_description cd
                     ON c.category_id = cd.category_id AND cd.language_id = 1
                 ORDER BY cd.name"
            );
            $stmt->execute();
            $map = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $map[(int)$row["category_id"]] = $row["name"];
            }
            return $map;
        } catch (PDOException $e) {
            error_log("OpenCartDb::getCategoryMap error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Find or create a category by name.
     */
    public function ensureCategory(string $name, int $parentId = 0): ?int
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT c.category_id
                 FROM {$this->prefix}category c
                 JOIN {$this->prefix}category_description cd
                     ON c.category_id = cd.category_id AND cd.language_id = 1
                 WHERE cd.name = ? AND c.parent_id = ?
                 LIMIT 1"
            );
            $stmt->execute([$name, $parentId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return (int)$row["category_id"];
            }

            $now = date("Y-m-d H:i:s");
            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                "INSERT INTO {$this->prefix}category
                    (parent_id, image, `top`, `column`, sort_order, status, date_added, date_modified)
                 VALUES (?, '', 0, 1, 1, 1, ?, ?)"
            );
            $stmt->execute([$parentId, $now, $now]);
            $categoryId = (int)$this->db->lastInsertId();

            $stmt = $this->db->prepare(
                "INSERT INTO {$this->prefix}category_description
                    (category_id, language_id, name, description, meta_title,
                     meta_description, meta_keyword)
                 VALUES (?, 1, ?, '', ?, '', '')"
            );
            $stmt->execute([$categoryId, $name, $name]);

            $stmt = $this->db->prepare(
                "INSERT INTO {$this->prefix}category_to_store (category_id, store_id)
                 VALUES (?, 0)"
            );
            $stmt->execute([$categoryId]);

            $this->db->commit();
            return $categoryId;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("OpenCartDb::ensureCategory error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Convert a string to a URL-safe slug.
     */
    public function nameToUrl(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace("/[^a-z0-9-]+/", "-", $slug);
        $slug = preg_replace("/-+/", "-", $slug);
        return trim($slug, "-");
    }
}

