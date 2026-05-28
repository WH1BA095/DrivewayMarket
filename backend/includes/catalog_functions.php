<?php
require_once 'config/db.php';

class Catalog {
    private $db;
    private $conn;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->conn = $this->db->getConnection();
    }
    
    public function getCategories() {
        $stmt = $this->conn->prepare("SELECT * FROM categories ORDER BY sort_order");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function getCategoryBySlug($slug) {
        $stmt = $this->conn->prepare("SELECT * FROM categories WHERE slug = ?");
        $stmt->execute([$slug]);
        return $stmt->fetch();
    }
    
    public function getTypesByCategory($category_id) {
        $stmt = $this->conn->prepare("SELECT * FROM product_types WHERE category_id = ? ORDER BY name");
        $stmt->execute([$category_id]);
        return $stmt->fetchAll();
    }
    
    public function getCarBrands() {
        $stmt = $this->conn->prepare("SELECT * FROM car_brands ORDER BY name");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function getModelsByBrand($brand_id) {
        $stmt = $this->conn->prepare("SELECT * FROM car_models WHERE brand_id = ? ORDER BY name");
        $stmt->execute([$brand_id]);
        return $stmt->fetchAll();
    }
    
    public function getProducts($filters = [], $limit = 12, $offset = 0) {
    $sql = "SELECT p.*, 
                   c.name as category_name, 
                   c.slug as category_slug,
                   pt.name as type_name, 
                   cb.name as brand_name, 
                   cm.name as model_name,
                   (p.quantity - p.reserved) as available
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN product_types pt ON p.type_id = pt.id
            LEFT JOIN car_brands cb ON p.brand_id = cb.id
            LEFT JOIN car_models cm ON p.model_id = cm.id
            WHERE 1=1";
    
    $params = [];
    
    if (!empty($filters['category_id'])) {
        $sql .= " AND p.category_id = ?";
        $params[] = $filters['category_id'];
    }
    
    if (!empty($filters['type_id'])) {
        $sql .= " AND p.type_id = ?";
        $params[] = $filters['type_id'];
    }
    
    if (!empty($filters['brand_id'])) {
        $sql .= " AND p.brand_id = ?";
        $params[] = $filters['brand_id'];
    }
    
    if (!empty($filters['model_id'])) {
        $sql .= " AND p.model_id = ?";
        $params[] = $filters['model_id'];
    }
    
    if (!empty($filters['in_stock'])) {
        $sql .= " AND (p.quantity - p.reserved) > 0";
    }
    
    if (!empty($filters['search'])) {
        $sql .= " AND (p.name LIKE ? OR p.description LIKE ? OR p.article LIKE ?)";
        $search = "%{$filters['search']}%";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
    }
    
    if (!empty($filters['sort'])) {
        switch ($filters['sort']) {
            case 'price_asc':
                $sql .= " ORDER BY p.price ASC";
                break;
            case 'price_desc':
                $sql .= " ORDER BY p.price DESC";
                break;
            case 'name_asc':
                $sql .= " ORDER BY p.name ASC";
                break;
            case 'name_desc':
                $sql .= " ORDER BY p.name DESC";
                break;
            case 'stock':
                $sql .= " ORDER BY (p.quantity - p.reserved) DESC, p.id DESC";
                break;
            default:
                $sql .= " ORDER BY p.id DESC";
                break;
        }
    } else {
        $sql .= " ORDER BY p.id DESC";
    }
    
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $this->conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

    public function getTotalProducts($filters = []) {
        $sql = "SELECT COUNT(*) as total FROM products p WHERE 1=1";
        $params = [];
        
        if (!empty($filters['category_id'])) {
            $sql .= " AND p.category_id = ?";
            $params[] = $filters['category_id'];
        }
        
        if (!empty($filters['type_id'])) {
            $sql .= " AND p.type_id = ?";
            $params[] = $filters['type_id'];
        }
        
        if (!empty($filters['brand_id'])) {
            $sql .= " AND p.brand_id = ?";
            $params[] = $filters['brand_id'];
        }
        
        if (!empty($filters['model_id'])) {
            $sql .= " AND p.model_id = ?";
            $params[] = $filters['model_id'];
        }
        
        if (!empty($filters['in_stock'])) {
            $sql .= " AND (p.quantity - p.reserved) > 0";
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result['total'];
    }
    
    public function getProductById($id) {
        $stmt = $this->conn->prepare("SELECT p.*, 
                                             c.name as category_name, 
                                             c.slug as category_slug,
                                             pt.name as type_name, 
                                             cb.name as brand_name, 
                                             cm.name as model_name,
                                             (p.quantity - p.reserved) as available
                                      FROM products p
                                      LEFT JOIN categories c ON p.category_id = c.id
                                      LEFT JOIN product_types pt ON p.type_id = pt.id
                                      LEFT JOIN car_brands cb ON p.brand_id = cb.id
                                      LEFT JOIN car_models cm ON p.model_id = cm.id
                                      WHERE p.id = ?");
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        
        if ($product) {
            if ($product['available'] > 10) {
                $product['stock_status'] = 'Много';
                $product['stock_class'] = 'in-stock-high';
                $product['stock_icon'] = 'fa-check-circle';
            } elseif ($product['available'] > 0) {
                $product['stock_status'] = 'В наличии';
                $product['stock_class'] = 'in-stock';
                $product['stock_icon'] = 'fa-check-circle';
            } elseif ($product['quantity'] > 0) {
                $product['stock_status'] = 'Скоро поступит';
                $product['stock_class'] = 'coming-soon';
                $product['stock_icon'] = 'fa-clock';
            } else {
                $product['stock_status'] = 'Нет в наличии';
                $product['stock_class'] = 'out-of-stock';
                $product['stock_icon'] = 'fa-times-circle';
            }
        }
        
        return $product;
    }
    
    public function checkAvailability($product_id, $requested_quantity = 1) {
        $stmt = $this->conn->prepare("SELECT (quantity - reserved) as available 
                                      FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $result = $stmt->fetch();
        
        if ($result) {
            return [
                'available' => $result['available'],
                'can_order' => $result['available'] >= $requested_quantity,
                'max_order' => min($result['available'], 99)
            ];
        }
        
        return [
            'available' => 0,
            'can_order' => false,
            'max_order' => 0
        ];
    }
    
    public function reserveProduct($product_id, $quantity = 1) {
        try {
            $this->conn->beginTransaction();
            
            $stmt = $this->conn->prepare("SELECT quantity, reserved, (quantity - reserved) as available 
                                          FROM products WHERE id = ? FOR UPDATE");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            
            if ($product && $product['available'] >= $quantity) {
                $stmt = $this->conn->prepare("UPDATE products 
                                              SET reserved = reserved + ? 
                                              WHERE id = ?");
                $stmt->execute([$quantity, $product_id]);
                
                $this->conn->commit();
                return true;
            }
            
            $this->conn->rollBack();
            return false;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }
    
    public function releaseProduct($product_id, $quantity = 1) {
        $stmt = $this->conn->prepare("UPDATE products 
                                      SET reserved = GREATEST(reserved - ?, 0) 
                                      WHERE id = ?");
        return $stmt->execute([$quantity, $product_id]);
    }
    
    public function getSaleProducts($limit = 10) {
        $stmt = $this->conn->prepare("SELECT p.*, 
                                             c.name as category_name,
                                             cb.name as brand_name,
                                             (p.quantity - p.reserved) as available
                                      FROM products p
                                      LEFT JOIN categories c ON p.category_id = c.id
                                      LEFT JOIN car_brands cb ON p.brand_id = cb.id
                                      WHERE p.quantity > 0
                                      ORDER BY RAND()
                                      LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
    
    public function getRecommendedProducts($product_id = null, $limit = 8) {
        if ($product_id) {
            $product = $this->getProductById($product_id);
            if ($product) {
                $stmt = $this->conn->prepare("SELECT p.*, 
                                                     c.name as category_name,
                                                     cb.name as brand_name,
                                                     (p.quantity - p.reserved) as available
                                              FROM products p
                                              LEFT JOIN categories c ON p.category_id = c.id
                                              LEFT JOIN car_brands cb ON p.brand_id = cb.id
                                              WHERE p.category_id = ? 
                                              AND p.id != ?
                                              AND p.quantity > 0
                                              ORDER BY RAND()
                                              LIMIT ?");
                $stmt->execute([$product['category_id'], $product_id, $limit]);
                return $stmt->fetchAll();
            }
        }
        
        $stmt = $this->conn->prepare("SELECT p.*, 
                                             c.name as category_name,
                                             cb.name as brand_name,
                                             (p.quantity - p.reserved) as available
                                      FROM products p
                                      LEFT JOIN categories c ON p.category_id = c.id
                                      LEFT JOIN car_brands cb ON p.brand_id = cb.id
                                      WHERE p.quantity > 0
                                      ORDER BY RAND()
                                      LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
}
?>