<?php
require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__); // or use project root if .env is there
$dotenv->load();
use Cloudinary\Cloudinary;

class CloudinaryService {
    private $cloudinary;

    public function __construct() {
        $this->cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => $_ENV['CLOUDINARY_CLOUD_NAME'],
                'api_key'    => $_ENV['CLOUDINARY_API_KEY'],
                'api_secret' => $_ENV['CLOUDINARY_API_SECRET']
            ],
            'url' => [
                'secure' => true
            ]
        ]);
    }

    /**
     * Upload image to Cloudinary
     * @param array $file Uploaded file from $_FILES
     * @return string|false URL of uploaded image or false if failed
     */
    public function uploadImage($file) {
        try {
            if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
                return false;
            }

            $upload = $this->cloudinary->uploadApi()->upload(
                $file['tmp_name'],
                ["folder" => "user_profiles"] // folder in Cloudinary
            );

            return $upload['secure_url'] ?? false;
        } catch (Exception $e) {
            error_log("Cloudinary upload failed: " . $e->getMessage());
            return false;
        }
    }
}
