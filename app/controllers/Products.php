<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Products extends Controller {

    private function api()
    {
        $this->call->database();
        $this->call->library('api');
        return $this->api;
    }

    private function authenticated()
    {
        $api = $this->api();
        return [$api, $api->require_jwt()];
    }

    private function admin_only($api, $payload)
    {
        if (($payload['role'] ?? '') !== 'admin' || !in_array('write', $payload['scopes'] ?? [], TRUE)) {
            $api->respond_error('Administrator access is required for product changes', 403);
        }
    }

    private function product_data($api, $body, $partial = FALSE)
    {
        $data = [];
        if (!$partial || array_key_exists('product_name', $body)) {
            $name = trim((string)($body['product_name'] ?? ''));
            if ($name === '' || strlen($name) > 100) {
                $api->respond_error('Product name is required and must be 100 characters or fewer', 422);
            }
            $data['product_name'] = $name;
        }
        if (!$partial || array_key_exists('description', $body)) {
            $data['description'] = trim((string)($body['description'] ?? ''));
        }
        if (!$partial || array_key_exists('price', $body)) {
            if (!isset($body['price']) || !is_numeric($body['price']) || (float)$body['price'] < 0) {
                $api->respond_error('Price must be a non-negative number', 422);
            }
            $data['price'] = number_format((float)$body['price'], 2, '.', '');
        }
        if (!$partial || array_key_exists('quantity', $body)) {
            if (!isset($body['quantity']) || filter_var($body['quantity'], FILTER_VALIDATE_INT) === FALSE || (int)$body['quantity'] < 0) {
                $api->respond_error('Quantity must be a non-negative integer', 422);
            }
            $data['quantity'] = (int)$body['quantity'];
        }
        return $data;
    }

    public function index()
    {
        [$api] = $this->authenticated();
        $api->require_method('GET');
        $products = $this->db->table('products')->order_by('id', 'DESC')->get_all();
        $api->respond(['data' => $products]);
    }

    public function store()
    {
        [$api, $payload] = $this->authenticated();
        $this->admin_only($api, $payload);
        $api->require_method('POST');
        $data = $this->product_data($api, $api->body());
        $id = $this->db->table('products')->insert($data);
        $product = $this->db->raw("SELECT * FROM products WHERE id = ?", [$id])->fetch(PDO::FETCH_ASSOC);
        $api->respond(['message' => 'Product created successfully', 'data' => $product], 201);
    }

    public function update($id)
    {
        [$api, $payload] = $this->authenticated();
        $this->admin_only($api, $payload);
        $api->require_method($_SERVER['REQUEST_METHOD']);
        $id = (int)$id;
        $data = $this->product_data($api, $api->body(), TRUE);
        if (!$data) {
            $api->respond_error('At least one product field is required', 422);
        }
        $updated = $this->db->table('products')->where('id', $id)->update($data);
        if (!$updated) {
            $exists = $this->db->raw("SELECT id FROM products WHERE id = ?", [$id])->fetch(PDO::FETCH_ASSOC);
            if (!$exists) {
                $api->respond_error('Product not found', 404);
            }
        }
        $product = $this->db->raw("SELECT * FROM products WHERE id = ?", [$id])->fetch(PDO::FETCH_ASSOC);
        $api->respond(['message' => 'Product updated successfully', 'data' => $product]);
    }

    public function destroy($id)
    {
        [$api, $payload] = $this->authenticated();
        $this->admin_only($api, $payload);
        $api->require_method('DELETE');
        $id = (int)$id;
        $deleted = $this->db->table('products')->where('id', $id)->delete();
        if (!$deleted) {
            $api->respond_error('Product not found', 404);
        }
        $api->respond(['message' => 'Product deleted successfully']);
    }
}
