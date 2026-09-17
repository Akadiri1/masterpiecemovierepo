<?php
ob_start();

session_start();
// die("Critical Maintenance in progress");
#Define App Path

define("D_PATH", dirname(dirname(__FILE__)));
CONST APP_PATH = D_PATH."/v1";
#load config
include D_PATH."/.env/config.php";
#load database
require APP_PATH."/models/model.php";
#load Controllers(functions)
require APP_PATH."/controllers/controller.php";

#load auth Controllers(functions)
require APP_PATH."/auth/auth_controller/controller.php";

#restore a remembered login before routing, so every page sees the session.
#PHP expires idle sessions after 24 minutes; this signs the user back in silently.
require APP_PATH."/lib/auth_remember.php";
if (isset($conn)) {
    auth_remember_try_login($conn);
    auth_sync_member($conn);
}
#load routes
// require APP_PATH."/routes/router.php";

// $websiteInfo = selectContent($conn, "read_website_info", ['visibility' => 'show']);
// $socialLinks = selectContent($conn, "social_links", ['visibility' => 'show']);
// $homeProduct = selectContent($conn, "home_product", ['visibility' => 'show']);
// $officeHours = selectContent($conn, "panel_office_hours", ['visibility' => 'show']);
// $websiteStyle = selectContent($conn, "website_status", ['visibility' => 'show']);

$_SESSION['color'] = "green";
// $_SESSION['debug'] = true;
//
// $websiteInfo is never loaded (the query above is commented out), so these
// have always been null. "?? null" keeps them null without logging a warning
// for every line on every request.
$site_name = $websiteInfo[0]['input_name'] ?? null;
$site_email = $websiteInfo[0]['input_email'] ?? null;
$site_email_2 = $websiteInfo[0]['input_email_2'] ?? null;
$site_email_from = $websiteInfo[0]['input_email_from'] ?? null;
$site_email_smtp_host = $websiteInfo[0]['input_email_smtp_host'] ?? null;
$site_email_smtp_secure_type = $websiteInfo[0]['input_email_smtp_secure_type'] ?? null;
$site_email_smtp_port = $websiteInfo[0]['input_email_smtp_port'] ?? null;
$site_email_password = $websiteInfo[0]['input_email_password'] ?? null;
$site_phone = $websiteInfo[0]['input_phone_number'] ?? null;
$site_phone_1 = $websiteInfo[0]['input_phone_number_1'] ?? null;
$site_address = $websiteInfo[0]['input_address'] ?? null;
$fbLink = $websiteInfo[0]['input_facebook'] ?? null;
$igLink = $websiteInfo[0]['input_instagram'] ?? null;
$linkedinLink = $websiteInfo[0]['input_linkedin'] ?? null;
$twitterLink = $websiteInfo[0]['input_twitter'] ?? null;
$description = $websiteInfo[0]['text_description'] ?? null;
$logo_directory = $websiteInfo[0]['image_1'] ?? null;
$domain = $_SERVER['HTTP_HOST'];

// die(var_dump($domain));
//
// if($websiteStyle[0]['status'] === "live"){
// if (count($websiteStyle) > 0 && $websiteStyle[0]['color'] !="") {
//   $style_color = $websiteStyle[0]['color'];
// }else{
//   // die(count($websiteStyle[0]['color']));
//   // unset($style_color);
//     }
// }
//
//
// if($websiteStyle[0]['status'] === "demo"){
// if (isset($_SESSION['color'])) {
//   $style_color = $_SESSION['color'];
// }
// }
//
//
// if($websiteStyle[0]['status'] === "demo"){
// if (isset($_SESSION['image_select'])) {
//   $logo_directory = $_SESSION['image_select'];
// }
// }


$fbid = "2213158278782711";




#load routes
include APP_PATH."/ajax/ajax_router/router.php";
// include APP_PATH."/routes/ajax_router.php";
// include APP_PATH."/payment/payment_router/router.php";
// include APP_PATH."/auth/auth_router/router.php";
include APP_PATH."/routes/admin_router.php";
include APP_PATH."/routes/router.php";


#load auth Controllers(functions)
// require APP_PATH."/auth_controller/controller.php";

 ?>
