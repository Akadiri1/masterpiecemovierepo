<?php
$headerFile = 'c:/wamp64/www/masterpiecemovie/v1/views/includes/header.php';
$sidebarFile = 'c:/wamp64/www/masterpiecemovie/v1/views/includes/sidebar.php';

$lines = file($headerFile);

// CSS is 601 to 729 (0-indexed 600 to 728)
$cssBlock = array_slice($lines, 600, 129);

// HTML is 768 to 864 (0-indexed 767 to 863)
$htmlBlock = array_slice($lines, 767, 97);

// Wrap CSS in <style>
array_unshift($cssBlock, "<style>\n");
$cssBlock[] = "</style>\n";

// Ensure PHP variables used in sidebar exist
$phpHeader = "<?php\n" .
    "\$isKidsMode = \$_SESSION['is_kids_mode'] ?? false;\n" .
    "\$displayName = \$_SESSION['firstName'] ?? \$_SESSION['username'] ?? 'Guest';\n" .
    "\$avatarPath = \$_SESSION['avatar_url'] ?? 'assets/images/user/user.jpg';\n" .
    "\$current_plan = \$_SESSION['plan_name'] ?? 'Free';\n" .
    "?>\n";

$sidebarContent = $phpHeader . implode("", $cssBlock) . implode("", $htmlBlock);
file_put_contents($sidebarFile, $sidebarContent);

// Rebuild header.php
$newHeader = [];
$inCss = false;
$inHtml = false;

for ($i = 0; $i < count($lines); $i++) {
    // Check if we are in the CSS block (600 to 728)
    if ($i >= 600 && $i <= 728) {
        if ($i == 600) {
            // Replace the entire block with nothing here, we'll put the include where the HTML was
        }
        continue;
    }
    
    // Check if we are in the HTML block (767 to 863)
    if ($i >= 767 && $i <= 863) {
        if ($i == 767) {
            $newHeader[] = "    <?php include __DIR__ . '/sidebar.php'; ?>\n";
        }
        continue;
    }
    
    $newHeader[] = $lines[$i];
}

file_put_contents($headerFile, implode("", $newHeader));
echo "Refactor complete!";
?>
