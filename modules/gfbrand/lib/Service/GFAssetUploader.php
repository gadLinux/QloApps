<?php
/**
 * 2026 GF Experiences
 *
 * Uploads an advisor photograph or partner logo into the module's own
 * uploads directory — story 1.11, AC-2.
 *
 * Not a Product Image: these images are not in the shop's catalogue and need
 * no size derivatives, so they are moved verbatim into
 * uploads/establishments/{advisors,partners}/ and referenced by file name.
 *
 * INFRASTRUCTURE LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFAssetUploader
{
    const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    const MAX_SIZE = 5242880; // 5 MB

    /** @var string Target directory name under uploads/establishments/. */
    private $directory;

    public function __construct($directory)
    {
        $this->directory = $directory;
    }

    /**
     * Move an uploaded file (from the 'file' field) into the asset directory.
     *
     * @param  string $field $_FILES field name.
     * @return array{success: bool, file: string, error: string}
     */
    public function upload($field)
    {
        if (empty($_FILES[$field]) || !is_array($_FILES[$field])) {
            return $this->fail('No file was uploaded.');
        }

        $upload = $_FILES[$field];

        if ((int) $upload['error'] !== UPLOAD_ERR_OK) {
            return $this->fail('Upload failed (error code ' . (int) $upload['error'] . ').');
        }

        if ((int) $upload['size'] === 0) {
            return $this->fail('The uploaded file is empty.');
        }

        if ((int) $upload['size'] > self::MAX_SIZE) {
            return $this->fail('The image is larger than 5 MB.');
        }

        $extension = strtolower(pathinfo((string) $upload['name'], PATHINFO_EXTENSION));

        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return $this->fail('Only JPG, PNG, GIF or WebP images are accepted.');
        }

        // Extension allow-listing only checks the client-supplied name; a
        // polyglot file (e.g. HTML/PHP wearing a .png name) would otherwise
        // land, unvalidated, in a web-served directory. getimagesize() reads
        // the actual file header, not the name.
        if (@getimagesize((string) $upload['tmp_name']) === false) {
            return $this->fail('The file is not a valid image.');
        }

        $targetDir = rtrim(_PS_ROOT_DIR_, '/') . '/uploads/establishments/' . $this->directory;

        if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true)) {
            return $this->fail('Could not create the upload directory.');
        }

        $fileName = date('Ymd-His') . '-' . uniqid('', true) . '.' . $extension;
        $targetPath = $targetDir . '/' . $fileName;

        if (!is_uploaded_file((string) $upload['tmp_name'])) {
            return $this->fail('The upload did not come from an HTTP POST.');
        }

        if (!move_uploaded_file((string) $upload['tmp_name'], $targetPath)) {
            return $this->fail('The file could not be moved into place.');
        }

        return ['success' => true, 'file' => $fileName, 'error' => ''];
    }

    /**
     * Remove a previously uploaded asset. Silently ignores names that are
     * not ours: a name must be a bare file name, and the file must exist in
     * the asset directory, or nothing is touched.
     *
     * @param  string $fileName
     * @return bool
     */
    public function remove($fileName)
    {
        $fileName = basename((string) $fileName);

        if ($fileName === '' || $fileName === '.') {
            return false;
        }

        $path = rtrim(_PS_ROOT_DIR_, '/') . '/uploads/establishments/' . $this->directory . '/' . $fileName;

        if (!is_file($path)) {
            return false;
        }

        return @unlink($path);
    }

    private function fail($message)
    {
        return ['success' => false, 'file' => '', 'error' => $message];
    }
}
