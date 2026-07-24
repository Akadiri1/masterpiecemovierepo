<?php
function doesPhoneNumberExist($dbconn, $input){
  $result = false;
  $stmt = $dbconn->prepare("SELECT * FROM users WHERE phone_number = :tp");
  $stmt->bindParam(":tp", $input);
  $stmt->execute();
  $count = $stmt->rowCount();
  if($count>0){
    $result = true;
  }
  return $result;
}

function doChangePassword($dbconn, $input,$hash_id){
  try{
    $hash = password_hash($input['pword'], PASSWORD_BCRYPT);
    #insert data
    $stmt = $dbconn->prepare("UPDATE users SET hash=:h WHERE hash_id=:hid");

    $data = [
      ':h' => $hash,
      ':hid' => $hash_id
    ];
    $stmt->execute($data);

    // $stmt2 = $dbconn->prepare("UPDATE user SET hash=:h WHERE hash_id=:hid");
    // #bind params...
    //
    // $stmt2->execute($data);

    $stmt3 = $dbconn->prepare("DELETE FROM verify WHERE email=:hid");
    #bind params...
    $data2 = [
      ':hid' => $hash_id
    ];
    $stmt3->execute($data2);

  }
  catch(PDOException $e){
    die("Something Went Wrong");
  }

  unset($_SESSION['user_to_edit']);
  session_destroy();

  $suc = 'Password Changed Successfully, You can now login';
  $message = $suc;
  header("Location:/login?success=$message");
}

function usersLogin($dbconn,$dbconn2,$sid, $input,$loc,$st){
  $stmt = $dbconn->prepare("SELECT * FROM users WHERE email = :e ");
  $stmt ->bindParam(":e", $input['email']);
  $stmt->execute();
  $row = $stmt->fetch(PDO::FETCH_BOTH);
  if($stmt->rowCount() !=1 || !password_verify($input['pword'], $row['hash'])){
    $suc = 'Invalid Email or Password';
    $message = preg_replace('/\s+/', '_', $suc);
    header("Location:login?err=$message");
  }else{
    // die($row['verification']);
    if( $row['verification'] !== "1" ){
      $suc = 'Dear '.ucwords($row['firstname']).', You Have Not been verified, kindly visit your email for verification link';
      $message = preg_replace('/\s+/', '_', $suc);
      header("Location:login?wn=$message");
      die;
    }




      extract($row);
// //session_start();
      if($row['level'] == 3 || $row['level'] == "MASTER"){
        $_SESSION['admin_id'] = $row['hash_id'];
        $_SESSION['admin_name'] = $row['firstname']." ".$row['lastname'];
      }

      $_SESSION['id'] = $row['hash_id'];
      if($row['usname'] == NULL){
          $_SESSION['username'] = "User";
      }else{
            $_SESSION['username'] = $usname;
      }
      // setLogin($dbconn,$hash_id);
      // die;
        header("Location:$loc");
    }
  }

  function forgotPassword($dbconn,$hash_id){

    $result = [];
    $token_s = 1;
    $ran = rand(0000000000,999999999);
    $tim = time();
    $process = $ran."MckodevVerification".$hash_id;
    $token = $tim."_".str_shuffle($process);


    $updatever = $dbconn->prepare("INSERT INTO verify VALUES(NULL,:em,:tk,:tks)");
    $data2 = [
      'em' => $hash_id,
      'tk' => $token,
      'tks' => $token_s
    ];
    $updatever->execute($data2);
    $result[] = $token;
    return $result;
  }


function getPhone($dbconn, $input){ #placeholders are just there
  $result = [];
  $stmt = $dbconn -> prepare("SELECT * FROM users WHERE email = :em");
  $stmt->bindParam(":em",$input);
  $stmt->execute();
  $row = $stmt->fetch(PDO::FETCH_BOTH);
  extract($row);

  $result[] = $phone_number;
  $result[] = $hash_id;
  $result[] = $firstname;

  return $result;
}


function doesEmailExist($dbconn, $input){ #placeholders are just there
  $result = false;
  $stmt = $dbconn -> prepare("SELECT * FROM users WHERE email = :em");
  $stmt->bindParam(":em",$input);
  $stmt->execute();
  $count = $stmt->rowCount();
  if($count>0){
    $result = true;
  }
  return $result;
}
function doesAdminEmailExist($dbconn, $input){ #placeholders are just there
  $result = false;
  $stmt = $dbconn -> prepare("SELECT * FROM admin WHERE email = :em");
  $stmt->bindParam(":em",$input);
  $stmt->execute();
  $count = $stmt->rowCount();
  if($count>0){
    $result = true;
  }
  return $result;
}

function doUserRegister($dbconn, $input){
  try{
  $rnd = rand(0000000000,9999999999);
    $split = $input['firstname'];
    $id = $rnd.cleans($split);
  $hash_id = time().str_shuffle($id);
  // $team = 0;
  // $point = 0;
  // $admin = 0;
  $visibility = "show";
  $hash = password_hash($input['pword'], PASSWORD_BCRYPT);
  #insert data
  $stmt = $dbconn->prepare("INSERT INTO users(firstname,lastname,phone_number, visibility, email,hash,hash_id,time_created,date_created) VALUES(:fn,:ln,:pn,:vs,:e,:h,:hid,NOW(),NOW())");
  #bind params...
  $data = [
    ':fn' => $input['firstname'],
  ':ln' => $input['lastname'],
  ':e' => $input['email'],
  ':pn' => $input['phonenumber'],
  ':h' => $hash,
  ':vs' => "show",
  // ':te' => $team,
  // ':as' => $admin,
  ':hid' => $hash_id
];
$stmt->execute($data);


$result = [];
$token_s = 1;
$ran = rand(0000000000,999999999);
$tim = time();
$process = $ran."MckodevGovernmentDashboardVerification".$hash_id;
$token = $tim."_".str_shuffle($process);


$updatever = $dbconn->prepare("INSERT INTO verify VALUES(NULL,:em,:tk,:tks,:en)");
$data2 = [
'em' => $hash_id,
'tk' => $token,
'tks' => $token_s,
'en' => $input['email']
];
$updatever->execute($data2);
$result[] = $token;
return $result;


}catch(PDOException $e){
  die($e->getMessage());

}

}

function doAdminRegister($dbconn, $input){
  try{
  $rnd = rand(0000000000,9999999999);
    $split = $input['firstname'];
    $id = $rnd.cleans($split);
  $hash_id = time().str_shuffle($id);

  $hash = password_hash($input['pword'], PASSWORD_BCRYPT);
  #insert data
  $stmt = $dbconn->prepare("INSERT INTO admin(firstname,lastname,email,hash,hash_id,time_created,date_created) VALUES(:fn, :ln,:e, :h,:hid,NOW(),NOW())");
  #bind params...
  $data = [
    ':fn' => $input['firstname'],
  ':ln' => $input['lastname'],
  ':e' => $input['email'],
  ':h' => $hash,
  ':hid' => $hash_id
];
$stmt->execute($data);



}catch(PDOException $e){
  die($e->getMessage());

}

}

/**
 * Send email notification to admin when a new user registers
 * @param PDO $dbconn Database connection
 * @param array $userData Array containing user data (firstname, lastname, email, phone_number)
 * @param string $site_name Site name for email
 * @param string $site_email Site email address
 */
function sendAdminRegistrationNotification($dbconn, $userData, $site_name, $site_email) {
  try {
    // Get admin email(s) - get all MASTER level admins
    $stmt = $dbconn->prepare("SELECT email, firstname FROM admin WHERE level = 'MASTER' LIMIT 5");
    $stmt->execute();
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($admins) == 0) {
      return; // No admins to notify
    }
    
    $firstname = htmlspecialchars($userData['firstname']);
    $lastname = htmlspecialchars($userData['lastname']);
    $email = htmlspecialchars($userData['email']);
    $phone = htmlspecialchars($userData['phone_number'] ?? 'Not provided');
    $date = date('F j, Y \a\t g:i A');
    
    $subject = "New User Registration - " . $site_name;
    
    $emailBody = '
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset="utf-8">
      <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(45deg, #4285f4, #34a853); padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .header h1 { color: white; margin: 0; font-size: 24px; }
        .content { background: #f9f9f9; padding: 30px; border: 1px solid #eee; }
        .user-info { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .user-info p { margin: 10px 0; }
        .label { font-weight: bold; color: #555; }
        .footer { text-align: center; padding: 20px; color: #888; font-size: 12px; }
        .btn { display: inline-block; padding: 12px 24px; background: #4285f4; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
      </style>
    </head>
    <body>
      <div class="container">
        <div class="header">
          <h1>🎉 New User Registration</h1>
        </div>
        <div class="content">
          <p>Hello Admin,</p>
          <p>A new user has just registered on <strong>' . $site_name . '</strong>.</p>
          
          <div class="user-info">
            <p><span class="label">Name:</span> ' . $firstname . ' ' . $lastname . '</p>
            <p><span class="label">Email:</span> ' . $email . '</p>
            <p><span class="label">Phone:</span> ' . $phone . '</p>
            <p><span class="label">Registered:</span> ' . $date . '</p>
          </div>
          
          <p>You can view and manage this user from the admin dashboard.</p>
          
          <center>
            <a href="https://' . $_SERVER['HTTP_HOST'] . '/admin/registration_dashboard.php" class="btn">View Dashboard</a>
          </center>
        </div>
        <div class="footer">
          <p>This is an automated notification from ' . $site_name . '</p>
        </div>
      </div>
    </body>
    </html>';
    
    // Send to each admin
    require_once APP_PATH . '/phpm/PHPMailerAutoload.php';
    
    foreach ($admins as $admin) {
      $mail = new PHPMailer;
      $mail->isSMTP();
      $mail->setFrom($site_email, $site_name);
      $mail->addAddress($admin['email'], $admin['firstname']);
      $mail->Username = $site_email;
      $mail->Password = getenv("EMAIL_PASSWORD");
      $mail->Host = 'smtp.gmail.com';
      $mail->Subject = $subject;
      $mail->Body = $emailBody;
      $mail->SMTPAuth = true;
      $mail->SMTPSecure = 'tls';
      $mail->Port = 587;
      $mail->isHTML(true);
      $mail->AltBody = "New user registered: $firstname $lastname ($email)";
      
      @$mail->send(); // Suppress errors - don't block registration if email fails
    }
    
  } catch (Exception $e) {
    // Log error but don't block registration
    error_log("Admin notification email failed: " . $e->getMessage());
  }
}

