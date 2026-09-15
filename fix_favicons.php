<?php
$dir = new RecursiveDirectoryIterator('c:/wamp64/www/masterpiecemovie/v1');
$ite = new RecursiveIteratorIterator($dir);
foreach($ite as $file) {
    if ($file->isFile() && $file->getExtension() == 'php') {
        $path = $file->getPathname();
        $content = file_get_contents($path);
        
        $newContent = preg_replace('/<link rel="(shortcut )?icon"[^>]*href="[^"]*"[^>]*>/i', '<link rel="shortcut icon" href="/assets/images/favicon.ico" />', $content);
        
        if ($content !== $newContent) {
            file_put_contents($path, $newContent);
            echo "Updated $path\n";
        }
    }
}
?>
