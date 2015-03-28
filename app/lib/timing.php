<?
function microtime_float() 
{ 
   list($usec, $sec) = explode(" ", microtime()); 
   return ((float)$usec + (float)$sec); 
}

/*
$time0 =microtime_float();

$handle = fopen("http://localhost/sortinonline.it/cddb/cddb.php?hello=minni+dio.it+cdex+1.5&cmd=cddb+query+af0b1e0c+12+150+19575+35775+55275+73950+92400+111976+130500+149475+168300+186300+192975+2848", "rb");
$contents = '';
while (!feof($handle)) {
 $contents .= fread($handle, 8192);
}
fclose($handle);
$timer1 =microtime_float()-$time0;
echo $contents;
echo "<br> in $timer1 Secs<br>";
*/
$time1 =microtime_float();

$handle = fopen("http://freedb.freedb.org/~cddb/cddb.cgi?cmd=cddb+query+af0b1e0c+12+150+19575+35775+55275+73950+92400+111976+130500+149475+168300+186300+192975+2848&hello=minni+dio.it+cdex+1.5&proto=5", "rb");
$contents = '';
while (!feof($handle)) {
 $contents .= fread($handle, 8192);
}
fclose($handle);
$timer2 =microtime_float()-$time1;
echo $contents;
echo "<br> in $timer2 Secs";

?>