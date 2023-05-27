<?php
require ('steamauth/steamauth.php');
require ('util.php');
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style>
            body {
                background-color: #111;
              	font-family: "Arial";
  				color: #ffffff;
  				font-size: 14px;
            }
        </style>
    </head>
    <body>
        <section>
            <div>
            <!-- Login process start -->
            <?php if (!isset($_SESSION['steamid'])) { ?>
                <a href="?login">
                    <img src="https://steamcommunity-a.akamaihd.net/public/images/signinthroughsteam/sits_02.png">
                </a>
            	<p>Log-in with steam to register a course account and receive the authkey.<br>We don't log anything about your steam profile aside from the SteamID itself.<br>Make sure your game details are public.<p>
            <?php return; } ?>
            <!-- Login process end -->

            <!-- After login. Gives you your apikey here. -->
            <form action="" method="get">
                <button name="logout" type="submit">Logout</button>
            </form>
            <p>Your apikey is: <b>
            <?php 
                include ('steamauth/userInfo.php'); 
                echo register_steam_account($steamprofile['steamid'], $steamprofile['timecreated']);
            ?>
            </b>
            </div>
        </section>
    </body>
</html>
