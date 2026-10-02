<?php
// Copyright 2016 Fleming Computer.
// All Rights Reserved.
?>
<?php
require_once('pizza.php');
if (strtolower(substr($GLOBALS['pizza']['pagePath'], -4)) == '.php')
{
    sleep(3);
    error404();
}
$captchaCode = '-----';
if (sessionExists()) {
    // Generate a 5-digit code without zeros.
    $captchaCode = '';
    for ($i = 1; $i <= 5; $i++)
        $captchaCode .= mt_rand(1, 9);
    beginSession();
    $GLOBALS['pizza']['session']->ss('captcha-code', $captchaCode);
}
// Create a whole image to contain the characters.
$wholeImage = imagecreatefromjpeg('captcha-background.jpg');
$wholeTrans = imagecolorallocate($wholeImage, 128, 128, 128);
imagefill($wholeImage, 0, 0, $wholeTrans);
// Convert the code string into a series of image tiles.
$xOffset = 5;
$yMax = 0;
for ($i = 0; $i < strlen($captchaCode); $i++)
{
    // Make a 20x20 white square with a 2-pixel black border.
    $image = imagecreatetruecolor(20, 20);
    $background = imagecolorallocate($image, mt_rand(128, 192), mt_rand(128, 192), mt_rand(128, 192));
    $border = imagecolorallocate($image, mt_rand(0, 80) * 3, mt_rand(0, 80) * 3, mt_rand(0, 80) * 3);
    $black = imagecolorallocate($image, 0, 0, 0);
    imagefill($image, 0, 0, $background);
    imagerectangle($image, 0, 0, 19, 19, $border);
    imagerectangle($image, 1, 1, 18, 18, $border);
    $char = substr($captchaCode, $i, 1);
    $angle = mt_rand(2, 7) * 5;
    $angleDirection = mt_rand(0, 1);
    if ($angleDirection == 1) $angle = -$angle;
    imagechar($image, 5, 6, 2, $char, $black);
    // Increase the size of the image and rotate it.
    $xSize = mt_rand(7, 10) * 4;
    $ySize = mt_rand(7, 10) * 4;
    $image2 = imagecreatetruecolor($xSize, $ySize);
    $trans = imagecolorallocate($image2, 128, 128, 128);
    imagecopyresampled($image2, $image, 0, 0, 0, 0, $xSize, $ySize, 20, 20);
    $image3 = imagerotate($image2, $angle, $trans);
    $trans = imagecolorclosest($image3, 128, 128, 128);
    imagecolortransparent($image3, $trans);
    // Copy the character square into the whole image.
    $vOffset = mt_rand(5, 20);
    imagecopymerge(
        $wholeImage, $image3,
        $xOffset, $vOffset,
        0, 0,
        imagesx($image3), imagesy($image3),
        100
    );
    $xOffset += imagesx($image3);
    if (imagesy($image3) + $vOffset > $yMax)
        $yMax = imagesy($image3) + $vOffset;
}
$width = $xOffset + 5;
$height = $yMax + 5;
$image = imagecreatetruecolor($width, $height);
$trans = imagecolorallocate($image, 128, 128, 128);
imagecolortransparent($image, $trans);
imagecopy($image, $wholeImage, 0, 0, 0, 0, $width, $height);
$border = imagecolorallocate($image, 48, 48, 48);
imagerectangle($image, 0, 0, $width - 1, $height - 1, $border);
imagetruecolortopalette($image, true, 256);
header('Expires: Thu, 19 Nov 1981 08:52:00 GMT');
header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
header('Pragma: no-cache');
header("Content-Type: image/gif");
imagegif($image);
?>
