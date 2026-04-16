<?php
/**
 * Google Drive upload helpers for portal media.
 */

// Autoload loaded via bootstrap.php

function getDriveService() {
    $credPath = GOOGLE_CREDS_PATH;
    $folderId = $_ENV['PORTAL_DRIVE_FOLDER_ID'] ?? '';

    if (!file_exists($credPath) || empty($folderId)) {
        return null;
    }

    $client = new \Google_Client();
    $client->setApplicationName('The Right Ring Portal');
    $client->setScopes([\Google_Service_Drive::DRIVE]);
    $client->setAuthConfig($credPath);
    $client->setAccessType('offline');

    return new \Google_Service_Drive($client);
}

/**
 * Upload a file to Google Drive.
 * Returns ['drive_file_id' => '...', 'thumbnail_url' => '...'] or null on failure.
 */
function uploadToDrive(string $localPath, string $filename, string $mimeType): ?array {
    $svc = getDriveService();
    if (!$svc) {
        return null;
    }

    $folderId = $_ENV['PORTAL_DRIVE_FOLDER_ID'];

    try {
        $fileMetadata = new \Google_Service_Drive_DriveFile([
            'name'    => $filename,
            'parents' => [$folderId],
        ]);

        $content = file_get_contents($localPath);

        $file = $svc->files->create($fileMetadata, [
            'data'              => $content,
            'mimeType'          => $mimeType,
            'uploadType'        => 'multipart',
            'fields'            => 'id,thumbnailLink,webViewLink',
            'supportsAllDrives' => true,
        ]);

        // Make file publicly readable for embedding
        $permission = new \Google_Service_Drive_Permission([
            'type' => 'anyone',
            'role' => 'reader',
        ]);
        $svc->permissions->create($file->getId(), $permission, [
            'supportsAllDrives' => true,
        ]);

        $thumbUrl = $file->getThumbnailLink() ?? "https://drive.google.com/thumbnail?id={$file->getId()}&sz=w400";

        return [
            'drive_file_id' => $file->getId(),
            'thumbnail_url' => $thumbUrl,
        ];
    } catch (\Exception $e) {
        error_log("Drive upload error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get a direct view/embed URL for a Drive file.
 */
function getDriveViewUrl(string $fileId): string {
    return "https://drive.google.com/file/d/{$fileId}/view";
}

function getDriveEmbedUrl(string $fileId): string {
    return "https://drive.google.com/file/d/{$fileId}/preview";
}
