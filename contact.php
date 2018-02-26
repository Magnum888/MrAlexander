<?php
$recepient = "kovel.web@yahoo.com";
$sitename = "Mr.Alexander restaurant";
$name = trim($_POST["name"]);
$email = trim($_POST["email"]);
$subject = trim($_POST["subject"]);
$message = trim($_POST["message"]);
$text = "Ім'я: $name \n Email: $email \n Тема: $subject \n Повідомлення: $message";
$pagetitle = "Нове повідомлення із сайту \"$sitename\"";
mail($recepient, $pagetitle, $text, "Content-type: text/plain; charset=\"utf-8\"\n From: $recepient");
?>
