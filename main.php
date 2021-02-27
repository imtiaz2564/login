<?php

include_once 'databaseconfig.php';


$config = new databaseconfig();
$bd = $config->getConnection();


function createTable($bd)
{
     createUserTable($bd);
     createLogTable($bd);
}

function createLogTable($bd)                    //table to track failed login attempts
{
    $logTable = "CREATE TABLE IF NOT EXISTS `failure_logs` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `username` varchar(100) NOT NULL,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
    )";
    $sql = $bd->prepare($logTable);
    $sql->execute();
    $sql->close();
}

function createUserTable($bd)
{
    $userTable = "CREATE TABLE IF NOT EXISTS `user`(
        username VARCHAR(100) NOT NULL PRIMARY KEY,
        password VARCHAR(100) NOT NULL,
        cookie_string VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL DEFAULT NULL,
        sign_out TIMESTAMP NULL DEFAULT NULL)";
    $sql = $bd->prepare($userTable);
    $sql->execute();
    $sql->close();
}

createTable($bd);

function createAdminUserIfNotExists($bd)
{
    $username = "admin";
    $password = "admin";
    $passcode = md5($username . $password);
    $sql = $bd->prepare("SELECT * FROM user WHERE username = '$username' and password = '$passcode'");
    $sql->execute();
    $loginResult = $sql->get_result();
    if (!mysqli_num_rows($loginResult)) {
        $loginData = $bd->prepare("INSERT INTO user (username, password)
        VALUES (? , ?)");
        $loginData->bind_param("ss", $username, $passcode);
        $loginData->execute();
        $loginData->close();

    }
}

createAdminUserIfNotExists($bd);


$loggedin = false;
$cookie_time = 60; // 60 seconds

if (isset($_GET["load"])) {
    $load = $_GET["load"];
}
if (isset($_POST['login'])) {

    $username = htmlspecialchars($_POST['username']); // to prevent xxs
    $passcode = htmlspecialchars($_POST['password']);
    $password = md5($username . $passcode );
    $userLoginSql = $bd->prepare("SELECT * FROM user WHERE username = ? and password = ?");
    $userLoginSql->bind_param("ss", $username, $password); // to prevent sql injection
    $userLoginSql->execute();
    $loginResult = $userLoginSql->get_result();
    $count = mysqli_num_rows($loginResult);
    if ($count == 1) {
        $current_date_time = date('Y-m-d H:i:s');
        $str = rand();
        $randomStr = md5($str);
        setcookie("random_string", $randomStr, time() + $cookie_time);
        setcookie("user_name", $username, time() + $cookie_time*10);
        $cookieSql = $bd->prepare("UPDATE user SET cookie_string = ? , last_login = ? WHERE username = ?"); // saving cookie
        $cookieSql->bind_param("sss", $randomStr, $current_date_time , $username);
        $cookieSql->execute();
        $cookieSql->close();
        header("Refresh:0");
    } else {
        $logsSql = $bd->prepare("INSERT INTO failure_logs(username)
        VALUES (?)");
        $logsSql->bind_param("s", $username); // to prevent sql injection
        $logsSql->execute();
        $logsSql->close();
    }


}

if (isset($_COOKIE['random_string'])) {

    $loggedin = true;
}

if ($loggedin) {

    $cookie = htmlspecialchars($_COOKIE["random_string"]);
    $username = htmlspecialchars($_COOKIE["user_name"]);

    $loggedinSql = $bd->prepare("SELECT * FROM user where cookie_string = '$cookie' and username = '$username'");
    $loggedinSql->execute();
    $loggedinResult = $loggedinSql->get_result();
    $loggedinSql->close();

    if(!mysqli_num_rows($loggedinResult)) {  // delete cookie if send incorrect cookie

        setcookie("random_string", "" , time()-$cookie_time);
        $current_date = date('Y-m-d H:i:s');
        $userLoginSql = $bd->prepare("UPDATE user SET cookie_string = '' , sign_out = '$current_date' WHERE username = '$username'");
        $userLoginSql->execute();
        $userLoginSql->close();
        include 'loginForm.php';
        return;
    }

    $user = $loggedinResult->fetch_assoc();

    if (isset($load) && $load === "unterseite") {

        echo "<h1>Unterseite</h1><p><a href='?load='>zur ersten Seite</a></p>";
        //datum uhrzeit des letzten logins anzeigen
        echo "Your last login date time: ".$user['last_login'];


    } else {

        echo "<h1>Erste Seite nach Login</h1><p><a href='/project/main.php?load=unterseite'>zur Unterseite</a></p>";
        //wenn gerade frisch eingeloggt --> hier den user namentlich begrüßen
        echo "Hallo ".$_COOKIE["user_name"]."<br>";


        //anzeigen, wie häufig seit dem letzten login das passwort falsch eingegeben wurde

        $countQuery = $bd->prepare("select * from failure_logs inner join user on user.username = failure_logs.username where (failure_logs.created_at > user.sign_out or user.sign_out is NULL) and user.username = '$username'");
        $countQuery->execute();
        $count = $countQuery->get_result();
        $countQuery->close();
        echo "Wrong passwords attempted: ". mysqli_num_rows($count) . " times";


    }

} else {

    if (isset($_COOKIE['user_name'])) {                            // updating signout time
        $username = htmlspecialchars($_COOKIE['user_name']);
        $current_date_time = date('Y-m-d H:i:s');
        $signoutSql = $bd->prepare("UPDATE user SET cookie_string = '' , sign_out = '$current_date_time' WHERE username = '$username'");
        $signoutSql->execute();
        $signoutSql->close();
        setcookie("user_name", "" , time()-$cookie_time*10);

    }

    include 'loginForm.php';

}


?>
