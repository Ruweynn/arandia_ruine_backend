<?php

defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ProductController extends Controller
{
    private $db;
    private $api;

    public function __construct()
    {
        parent::__construct();
        $this->db = $this->call->database();
        $this->api = $this->call->library('api');
    }

    private function require_auth()
    {
        $payload = $this->api->require_jwt();
        $scopes = $payload['scopes'] ?? [];
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        $required = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            ? ['read', 'write', 'delete']
            : ['read'];

        if (!array_intersect($required, $scopes)) {
            $this->api->respond_error('Forbidden', 403);
        }

        return $payload;
    }

    private function validate_product(array $data)
    {
        $product_name = trim((string) ($data['product_name'] ?? ''));
        $price = (float) ($data['price'] ?? 0);
        $quantity = (int) ($data['quantity'] ?? 0);

        if ($product_name === '') {
            throw new InvalidArgumentException('The product name is required.');
        }

        if (strlen($product_name) > 100) {
            throw new InvalidArgumentException('The product name must be at most 100 characters.');
        }

        if ($price < 0) {
            throw new InvalidArgumentException('The price cannot be negative.');
        }

        if ($quantity < 0) {
            throw new InvalidArgumentException('The quantity cannot be negative.');
        }

        return [
            'product_name' => $product_name,
            'description' => trim((string) ($data['description'] ?? '')),
            'price' => number_format($price, 2, '.', ''),
            'quantity' => $quantity,
        ];
    }

    public function index()
    {
        $this->api->require_method('GET');
        $this->require_auth();

        $stmt = $this->db->raw(
            'SELECT id, product_name, description, price, quantity, created_at, updated_at
             FROM products ORDER BY id DESC'
        );

        $this->api->respond($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function store()
    {
        $this->api->require_method('POST');
        $this->require_auth();

        try {
            $product = $this->validate_product($this->api->body());
        } catch (InvalidArgumentException $e) {
            $this->api->respond_error($e->getMessage(), 422);
        }

        $product['created_at'] = date('Y-m-d H:i:s');
        $this->db->table('products')->insert($product);
        $product['id'] = $this->db->last_id();

        $this->api->respond([
            'message' => 'Product created successfully.',
            'product' => $product,
        ], 201);
    }

    public function update($id)
    {
        if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['PUT', 'PATCH'], true)) {
            $this->api->require_method('PUT');
        }
        $this->require_auth();

        if (!is_numeric($id)) {
            $this->api->respond_error('Invalid product ID.', 400);
        }

        try {
            $product = $this->validate_product($this->api->body());
        } catch (InvalidArgumentException $e) {
            $this->api->respond_error($e->getMessage(), 422);
        }

        $existing = $this->db->raw('SELECT id FROM products WHERE id = ?', [(int) $id]);
        if ($existing->fetch() === false) {
            $this->api->respond_error('Product not found.', 404);
        }

        $product['updated_at'] = date('Y-m-d H:i:s');
        $stmt = $this->db->raw(
            'UPDATE products SET product_name = ?, description = ?, price = ?, quantity = ?, updated_at = ? WHERE id = ?',
            [$product['product_name'], $product['description'], $product['price'], $product['quantity'], $product['updated_at'], (int) $id]
        );

        $this->api->respond([
            'message' => 'Product updated successfully.',
            'updated' => $stmt->rowCount(),
        ]);
    }

    public function delete($id)
    {
        $this->api->require_method('DELETE');
        $this->require_auth();

        if (!is_numeric($id)) {
            $this->api->respond_error('Invalid product ID.', 400);
        }

        $stmt = $this->db->raw('DELETE FROM products WHERE id = ?', [(int) $id]);
        if ($stmt->rowCount() === 0) {
            $this->api->respond_error('Product not found.', 404);
        }

        $this->api->respond(['message' => 'Product deleted successfully.']);
    }
}
