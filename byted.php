<?php
// Application constants and file-type groups

define('MAX_UPLOAD_BYTES', 50 * 1024 * 1024);

$typeGroups = [
    'presentation' => ['ppt', 'pptx', 'odp', 'key'],
    'pdf'          => ['pdf'],
    'docs'         => ['doc', 'docx', 'odt', 'rtf', 'txt'],
    'spreadsheet'  => ['xls', 'xlsx', 'ods', 'csv'],
    'video'        => ['mp4', 'mkv', 'avi', 'mov', 'webm'],
    'audio'        => ['mp3', 'wav', 'ogg', 'flac', 'm4a'],
    'image'        => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']
];
