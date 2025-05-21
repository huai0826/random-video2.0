<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Max-Age: 86400');

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }
    exit(0);
}

$config = [
    'video_dir' => realpath(__DIR__.'/video'),
    'allowed_ext' => ['mp4', 'mov', 'avi', 'mkv', 'webm', 'flv'],
    'base_url' => './video/', //此处更改视频文件目录
    'debug' => false
];

try {
    if ($config['debug']) {
        error_log(date('[Y-m-d H:i:s]')." API请求开始\n", 3, 'api.log');
    }

    if (!is_dir($config['video_dir'])) {
        throw new Exception("视频目录不存在或不可访问: " . $config['video_dir']);
    }

    $videoFiles = [];
    $di = new RecursiveDirectoryIterator($config['video_dir'], FilesystemIterator::SKIP_DOTS);
    $iterator = new RecursiveIteratorIterator($di);

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $ext = strtolower($file->getExtension());
            if (!in_array($ext, $config['allowed_ext'])) continue;

            // 修复路径处理逻辑
            $relativePath = ltrim(
                str_replace($config['video_dir'], '', $file->getPathname()),
                '/\\' // 移除开头可能存在的斜杠
            );
            
            // 统一路径分隔符
            $relativePath = str_replace('\\', '/', $relativePath);
            
            // 分段编码路径组件
            $pathParts = explode('/', $relativePath);
            $encodedParts = array_map('rawurlencode', $pathParts);
            $encodedPath = implode('/', $encodedParts);

            // 安全拼接URL
            $videoUrl = rtrim($config['base_url'], '/') . '/' . ltrim($encodedPath, '/');

            // 验证文件可访问性
            if (!file_exists($file->getPathname())) {
                if ($config['debug']) {
                    error_log("文件不存在: ".$file->getPathname()."\n", 3, 'api.log');
                }
                continue;
            }

            $videoFiles[] = [
                'filename' => $file->getFilename(),
                'path' => $relativePath,
                'url' => $videoUrl,
                'size' => $file->getSize(),
                'mtime' => $file->getMTime()
            ];
        }
    }

    if (empty($videoFiles)) {
        throw new Exception("没有找到可用的视频文件");
    }

    // 随机选择视频
    $randomVideo = $videoFiles[array_rand($videoFiles)];

    // 返回JSON并禁用斜杠转义
    echo json_encode([
        'status' => 'success',
        'data' => $randomVideo
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'debug_info' => $config['debug'] ? [
            'video_dir' => $config['video_dir'],
            'base_url' => $config['base_url']
        ] : null
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
