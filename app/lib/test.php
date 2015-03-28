<?
 $buffer ="";
 $buf_pos =0;
 $buf_size =0; 
 function my_fgets($file) {
   global $buffer, $buf_pos, $buf_size;
   $result ="";
   do {
     if ($buf_pos==$buf_size) {
       $buffer =fread($file, 4096); //1048576
       $buf_size =strlen($buffer);
       $buffer .="\0"; 
       if ($buf_size == 0) return false; 
       $buf_pos =0;
       //echo "buf_size=$buf_size \r\n";
     }
     $result .=$buffer{$buf_pos};
     $buf_pos++;
   } while ($buffer{$buf_pos}!="\n");
   $buf_pos++;
   return $result;
 } 

if(!function_exists("file_put_contents")) {
function file_put_contents($filename, $data, $file_append = false) {
  $fp = fopen($filename, (!$file_append ? "w+" : "a+"));
  if(!$fp) {
    trigger_error("file_put_contents cannot write in file.", E_USER_ERROR);
    return;
  }
  fwrite($fp, $data);
  fclose($fp);
}
}

function microtime_float() 
{ 
   list($usec, $sec) = explode(" ", microtime()); 
   return ((float)$usec + (float)$sec); 
}

function my_file($filename) {
 $Lines =array();
 $file =fopen($filename, "r");
 //while (!feof($file)) {
 while (true) {
   $line = fgets($file);
   if (feof($file)) break;
   else $Lines[] = $line;
 }
 fclose($file);
 return $Lines;
}

function GetDisclen($St)
{
 $result = 0;
 $npos = strrpos($St, "/");
 if ($npos !== false) {
  $dummy ="";
  $Disclen =0;
  sscanf(substr($St, $npos+1), "%d %s", $Disclen, $dummy);
  return $Disclen;
 }
 return $result;  
}

function SplitCmd($cmd, $toupper=false)
{
 $result = array();
 $elem = "";
 if ($toupper) { $cmd = strtoupper($cmd); }
 for ($i = 0; $i <= strlen($cmd)-1; $i++) {
   if ($cmd[$i] == ' ') {
     $result[] = $elem;
     $elem = "";
   }
   else { 
     $elem .= $cmd[$i];
     if ($i == strlen($cmd)-1) $result[] = $elem;
   }
 }
 return $result;
}
 
function GetTracksLengths($file, &$TrackOffsets, &$Disclen) {
  $endOffsets = false;
  while (!$endOffsets) {
    $line = fgets($file);
    $Uline = strtoupper($line);

    //In some cases no div lines between Track Frames and Disc length (7912cb19)
    $npos = strpos($Uline, "DISC LENGTH:");
    if ($npos !== false) { 
      $spos = strpos($Uline, "SECOND");
      if ($spos === false) { $Disclen = intval(trim(substr($line, $npos+12))); }
      else { $Disclen = intval(trim(substr($line, $npos+12, ($spos-$npos-12)))); }

      break;
    }
    $endOffsets = (strlen($line)==2);
    if (!$endOffsets) { 
      $curTrack = intval(trim(substr($line, 1)));
      $endOffsets = ($curTrack<1);
      if (!$endOffsets) { $TrackOffsets[] .=$curTrack; }
    }
  }//while TRACK FRAME OFFSETS ends

  if ($Disclen == 0) {
    $endOffsets = false;
    while (!$endOffsets) {
      $line = strtoupper(fgets($file));
      $npos = strpos($line, "DISC LENGTH:");
      $endOffsets = ($npos !== false);
      if ($endOffsets)  {
        $spos = strpos($line, "SECOND");
        if ($spos === false) { $Disclen = intval(trim(substr($line, $npos+12))); }
        else { $Disclen = intval(trim(substr($line, $npos+12, ($spos-$npos-12)))); }
             }
    }//while DISC LENGTH ends
  }
}

function is_fuzzy_match($offtab1, $offtab2, $nsecs1, $nsecs2, $ntrks)
{
  global $FUZZY_FACTOR, $FUZZY_DIV, $CDDBFRAMEPERSEC;
  $i =0;
  $avg =0;
  $lo1 =0;
  $lo2 =0;
   
  // Check the difference between track offsets.
  for($i; $i < $ntrks; $i++) {
    $lo1 = $offtab1[$i] - $lo1;
    $lo2 = $offtab2[$i] - $lo2;

    $x = $lo1 - $lo2;
    if ($x < 0) $x *= -1;
    $avg += $x;

    // Track diff too great.
    if ($x > $FUZZY_FACTOR) return false;

    $lo1 = $offtab1[$i];
    $lo2 = $offtab2[$i];
  }

  // Compare disc length as if it were a track.
  $lo1 = ($nsecs1 * $CDDBFRAMEPERSEC) - $lo1;
  $lo2 = ($nsecs2 * $CDDBFRAMEPERSEC) - $lo2;

  $x = $lo1 - $lo2;
  if ($x < 0) $x *= -1;

  // Track diff too great.
  if ($x > $FUZZY_FACTOR) return false;

  $avg += $x;
  $avg /= $ntrks + 1;

  return (($i == $ntrks) && ($avg <= ($FUZZY_FACTOR / $FUZZY_DIV)));
}

function Test1() {
$T2 =0;
$T3 =0;
$T4A =0;
$T5A =0;
$T4B =0;
$T4B_1 =0;
$T4B_2 =0;
$T4B_3 =0;
$T4B_4 =0;
$T4B_5 =0;
$T4B_6 =0;
$T4B_7 =0;
$T5B =0;
$Category ="tests";

$DiscID ="0212be13";
$QUERY_CHECK_TRACKNUM =true;
 $NTracks = 19;

 $TracksOffset = array();
 $TotalSec = 4800;
 $ExactMatch = array();


$time0 =microtime_float();

   $file = fopen("./test/test", "r");

    $SearchDiscID = true;
    while (!feof($file)) {
$time1 =microtime_float();
      $line = fgets($file);
      if ($line{0}!='#') continue; //I'm here only if i search for #FILENAME

$time2 =microtime_float(); $T2 +=$time2-$time1; 
     // $Uline =strtoupper($line);
$time3 =microtime_float(); $T3 +=$time3-$time2;

      $npos = strpos($line, "FILENAME=");
$time4a =microtime_float(); $T4A +=$time4a-$time3;
      if ($npos !== false) {
$time4b =microtime_float();
        $aux1 =substr($line, $npos+9);
$time4b_1 =microtime_float(); $T4B_1 +=$time4b_1-$time4b;
        $aux2 =trim($aux1);
$time4b_2 =microtime_float(); $T4B_2 +=$time4b_2-$time4b_1;
        $aux3 =strtolower($aux2);
$time4b_3 =microtime_float(); $T4B_3 +=$time4b_3-$time4b_2;
        if ($aux3 == $DiscID) {//My DiscID
$time4b =microtime_float();
          $AddThis =true;

          if ($QUERY_CHECK_TRACKNUM) {
            //search TRACK FRAME OFFSETS start
            $dpos =0;
            do {
              if (feof($file)) die("403 Database entry is corrupt. (DiscID=$DiscID)\r\n");
              $line = fgets($file);
              if ($line{0}!='#') { $dpos =false; }
              else { $dpos = strpos(strtoupper($line), "TRACK FRAME OFFSETS:"); }
            } while ($dpos === false);

            $myTrackOffsets = array();
            $myDisclen =0;
            GetTracksLengths($file, $myTrackOffsets, $myDisclen);
$time4b_4 =microtime_float(); $T4B_4 +=$time4b_4-$time4b;
  
            $AddThis = (is_fuzzy_match($TracksOffset, $myTrackOffsets, $TotalSec, $myDisclen, $NTracks));
$time4b_5 =microtime_float(); $T4B_5 +=$time4b_5-$time4b_4;
          }//QUERY_CHECK_TRACKNUM

          if ($AddThis) {
$time4b =microtime_float();
            //search first DTITLE
            $dpos =false;
            do {
              if (feof($file)) die("403 Database entry is corrupt. (DiscID=$DiscID)\r\n");
              $line = fgets($file);
              if ($line{0}=='#') { $dpos =false; }
              else { $dpos = strpos(strtoupper($line), "DTITLE="); }
            } while ($dpos === false);
            $DTitle = trim(substr($line, $dpos+7));
            //add others DTITLE
            do {
              if (feof($file)) break;
              $line = fgets($file);
              if ($line{0}=='#') break;
              $dpos = strpos(strtoupper($line), "DTITLE=");
              if ($dpos !== false) $DTitle =$DTitle.trim(substr($line, $dpos+7));
            } while ($dpos !== false);
$time4b_6 =microtime_float(); $T4B_6 +=$time4b_6-$time4b;

            if (!is_null($DTitle)) { 
              $ExactMatch[] = "$Category $DiscID $DTitle"; 
              break;//thake only the first, i cannot read more than one in "cddb read categ discid"
            } 
$time4b_7 =microtime_float(); $T4B_7 +=$time4b_7-$time4b_6;
          }
        }//My DiscID
      }//Another DiscID
    }//while feof($file)

/* Test read all 
   $SearchDiscID = true;
   while (!feof($file)) {
$time1 =microtime_float();
     $pline =fgets($file);
     if ($pline{0}!='#') continue; //I'm here only if i search for #FILENAME or # TRACK FRAME OFFSETS

$time2 =microtime_float(); $T2 +=$time2-$time1; 
     $line = strtoupper($pline);
$time3 =microtime_float(); $T3 +=$time3-$time2;

     if ($SearchDiscID) {
      $npos = strpos($line, "#FILENAME="); 
$time4a =microtime_float(); $T4A +=$time4a-$time3;
      if ($npos !== false) {
         $DiscID = strtolower(trim(substr($line, $npos+10)));
         $SearchDiscID = false;
       }
$time5a =microtime_float(); $T5A +=$time5a-$time4a;
     }
     else {
       $npos = strpos($line, "TRACK FRAME OFFSETS:"); 
$time4b =microtime_float(); $T4B +=$time4b-$time3;
       if ($npos !== false) {
$time4b_1 =microtime_float(); $T4B_1 +=$time4b_1-$time4b;
         $TrackOffsets = array();
$time4b_2 =microtime_float(); $T4B_2 +=$time4b_2-$time4b_1;
         $Disclen =0;
         GetTracksLengths($file, $TrackOffsets, $Disclen);
$time4b_3 =microtime_float(); $T4B_3 +=$time4b_3-$time4b_2;

         //search first DTITLE
         $dpos =0;
         do {
           if (feof($file)) die("403 Database entry is corrupt. (DiscID=$DiscID)\r\n");
           $line = fgets($file);
           $Uline = strtoupper($line);
           $dpos = strpos($Uline, "DTITLE=");
         } while ($dpos === false);
         $DTitle = trim(substr($line, $dpos+7));
$time4b_4 =microtime_float(); $T4B_4 +=$time4b_4-$time4b_3;

         //add others DTITLE
         do {
           if (feof($file)) break;
           $line = fgets($file);
           $Uline = strtoupper($line);
           $dpos = strpos($Uline, "DTITLE=");
           if ($dpos !== false) $DTitle =$DTitle.trim(substr($line, $dpos+7));
         } while ($dpos !== false);

$time4b_5 =microtime_float(); $T4B_5 +=$time4b_5-$time4b_4;

         //Warnings
         if (($Disclen<5) || ($Disclen>5400)) { 
           echo("\r\nWARNING ($Category/$DiscID): Disclen = $Disclen"); $prevWarning =true;
           file_put_contents("./log/cddb_build_fuzzy.log", "\r\nWARNING ($Category/$DiscID): Disclen = $Disclen\r\n", true);
         }
         if ((count($TrackOffsets)==0) || (count($TrackOffsets)>99)) {
           if (!$prevWarning) echo("\r\nWARNING ($Category/$DiscID):");
           echo(" Tracks = ".count($TrackOffsets)); $prevWarning =true; 
           file_put_contents("./log/cddb_build_fuzzy.log", "\r\nWARNING ($Category/$DiscID): Tracks = ".count($TrackOffsets)."\r\n", true);
         }

$time4b_6 =microtime_float(); $T4B_6 +=$time4b_6-$time4b_5;

         if (!is_null($DTitle)) {
           $NumTracks =count($TrackOffsets);
           $file_index = fopen("./test/$NumTracks", "a");
           $OutStr = "$DTitle/$Disclen $DiscID";
           for ($iTracks=0; $iTracks<$NumTracks; $iTracks++) { $OutStr .=" ".$TrackOffsets[$iTracks]; }
           fwrite($file_index, "$OutStr\n");
//           fwrite($file_index,"$DTitle/$Disclen $DiscID");
//           for ($iTracks=0; $iTracks<$NumTracks; $iTracks++) { fwrite($file_index, " ".$TrackOffsets[$iTracks]); }
//           fwrite($file_index, "\n");
           fclose($file_index); 
         } 
$time4b_7 =microtime_float(); $T4B_7 +=$time4b_7-$time4b_6;

         $SearchDiscID =true;
       }//"TRACK FRAME OFFSETS:"
$time5b =microtime_float(); $T5B +=$time5b-$time3;
     }
   }//while feof($file)
*/

   fclose($file);

echo "T2 =$T2;<br>
T3 =$T3;<br>
T4A =$T4A;<br>
T5A =$T5A;<br>
T4B =$T4B;<br>
T4B_1 =$T4B_1;<br>
T4B_2 =$T4B_2;<br>
T4B_3 =$T4B_3;<br>
T4B_4 =$T4B_4;<br>
T4B_5 =$T4B_5;<br>
T4B_6 =$T4B_6;<br>
T4B_7 =$T4B_7;<br>
T5B =$T5B;<br>";


die("TotalTime =".(microtime_float()-$time0));
}

function Test2() {
   $Lines = my_file("./test/a13");
   $IndexByDisclen =array();
   $Disclens =array();
   $file = fopen("./test/a13ii", "w");

   $filepos = 0;
   for ($i =0; $i<count($Lines); $i++) {
     $line =$Lines[$i];
     $Disclen =GetDisclen($line);
     $ipos = floor($Disclen / 50);
     if (!isset($IndexByDisclen[$ipos])) { echo "$Disclen : Set $ipos=$filepos<br>";  $IndexByDisclen[$ipos] = $filepos; $Disclens[$ipos] = $Disclen; }
     $filepos +=strlen($line);
   }
   echo "...".$ipos."<br>";
   $IndexStr ="";
   $lastset =0;
   for ($i=0; $i<=$ipos; $i++) {
      if (isset($IndexByDisclen[$i])) $lastset = $IndexByDisclen[$i]; 
      $IndexStr .= "$lastset "; 
   }
   $IndexStr = rtrim($IndexStr)."\n";

   fwrite($file, $IndexStr); 
   for ($i =0; $i<count($Lines); $i++) {
     fwrite($file, $Lines[$i]); 
   }
   
   fclose($file);

   $ifile = fopen("./test/a13ii", "r");
   $iIndexStr = fgets($ifile);
   $Indexes = explode(" ", $iIndexStr);
   $FirstOfs = strlen($iIndexStr);

   echo "...".count($Indexes)."<br>";
   for ($i =0; $i<count($Indexes); $i++) {
     fseek($ifile, $Indexes[$i]+$FirstOfs);
     $line =fgets($ifile);
     $Disclen =GetDisclen($line);
     if (!isset($Disclens[$i])) echo "Index $i REFER to $Disclen -> $line<br>";
     else {
           if ($Disclen != $Disclens[$i]) echo "NOT ";
           echo "Index $i IS $Disclen -> $line<br>";  
     }
   } 
   fclose($ifile);


die("ok");
}

function Test3() {
 $time_startx =microtime_float();
/*
 $Lines1 =array();
 $file =fopen("./test/a13", "r");
 while (!feof($file)) {
   $line = fgets($file);
   if (!feof($file)) $Lines1[] = $line;
 }
 fclose($file);
*/
 $Lines1 = my_file("./test/a13");
 $timer = (microtime_float()-$time_startx); 
 echo "<br>timer1=$timer<br> count".count($Lines1);
 echo "<br>".$Lines1[count($Lines1)-1];


 $time_startx3 =microtime_float();
 $Lines = file("./test/a13");
 $timer3 = (microtime_float()-$time_startx3); 
 echo "<br>timer3=$timer3<br> count".count($Lines);
 echo "<br>".$Lines[count($Lines)-1];

 for ($i=0; $i<count($Lines); $i++) {
  if ($Lines[$i] != $Lines1[$i]) echo "<br>Line $i Diff<br>$Lines[$i]<br>$Lines1[$i]<br>";
 }

/*
 $time_startx2 =microtime_float();
 $file =fopen("./test/a13", "r");
 do{
   $line = my_fgets($file);
   //echo "$line\n";
 } while ($line !== false);
 fclose($file);
 $timer2 = (microtime_float()-$time_startx2);
 echo "timer2=$timer2\r\n";
*/
}

function cmpByDiscID($a, $b) 
{
 $a_id =0;
 $b_id =0;
 $dummy =0;
 sscanf($a, "%x %d", $a_id, $dummy);
 sscanf($b, "%x %d", $b_id, $dummy);
   
 if ($a_id == $b_id) { return 0; }
 return ($a_id < $b_id) ? -1 : 1;
}


function Test4() {
     $file_name = "./test/00";
     $Lines = my_file($file_name);
   
     $IndexBy23 =array();
   $Disclens =array();
     $file = fopen("./test/00ii", "w");

     $filepos = 0;
     for ($i =0; $i<count($Lines); $i++) {
       $line =$Lines[$i];
       sscanf(substr($line, 2, 2), "%02x", $ipos);
       if (!isset($IndexBy23[$ipos])) { $IndexBy23[$ipos] = $filepos; $Disclens[$ipos] = substr($line, 0, 8); }
       $filepos +=strlen($line);
     }
     $IndexStr ="";
     $lastset =0;
     for ($i=0; $i<=$ipos; $i++) {
        if (isset($IndexBy23[$i])) $lastset = $IndexBy23[$i]; 
        $IndexStr .= "$lastset "; 
     }
     $IndexStr = rtrim($IndexStr)."\n";

     fwrite($file, $IndexStr); 
     for ($i =0; $i<count($Lines); $i++) {
       fwrite($file, $Lines[$i]); 
     }
   
     fclose($file);

   $ifile = fopen("./test/00ii", "r");
   $iIndexStr = fgets($ifile);
   $Indexes = explode(" ", $iIndexStr);
   $FirstOfs = strlen($iIndexStr);

   echo "...".count($Indexes)."<br>";
   for ($i =0; $i<count($Indexes); $i++) {
     fseek($ifile, $Indexes[$i]+$FirstOfs);
     $line =fgets($ifile);
     $Disclen =substr($line, 0, 8);
     if (!isset($Disclens[$i])) echo "Index $i REFER to $Disclen -> $line<br>";
     else {
           if ($Disclen != $Disclens[$i]) echo "NOT ";
           echo "Index $i IS $Disclen -> $line<br>";  
     }
   } 
   fclose($ifile);
die("ok");
}

function GetTracksOfs($line)
{
//"$Disclen $DiscID $DiscID_fpos/Tracks..."
  $npos =strpos($line, "/");
  if ($npos !== false) {
   return explode(" ", substr($line, $npos+1));
  }
  return false;
}

function TestScanf()
{
 $dummy ="";

 //sscanf("18500 00ab0ecd 1654321/150 180 190 200 210 250 260", "%d %x %d/%s", $myDisclen, $myDiscId, $myDiscId_fpos, $dummy);
 sscanf("800a8b0a 254090", "%x %d", $a_id, $a_pos);
 sscanf("8005ef0a 733", "%x %d", $b_id, $b_pos);
 echo "$a_id, $a_pos<br>";
 echo "$b_id, $b_pos<br>";

 sscanf("800a8b0a 254090", "%s %d", $a_s, $a_pos);
 sscanf("8005ef0a 733", "%s %d", $b_s, $b_pos);
 $a_id =hexdec($a_s);
 $b_id =hexdec($b_s);
 echo "$a_id, $a_pos<br>";
 echo "$b_id, $b_pos<br>";

 sscanf("18500 00ab0ecd 1654321/150 180 190 200 210 250 260", "%d %s %d/%s", $myDisclen, $myDiscId, $myDiscId_fpos, $dummy);

 echo "$myDisclen, $myDiscId, $myDiscId_fpos, $dummy<br>";
   $myTracksOffsets =GetTracksOfs("18500 00ab0ecd 1654321/150 180 190 200 210 250 260"); 
   for ($i =0; $i<count($myTracksOffsets); $i++) {
     echo $myTracksOffsets[$i]."<br>";
   } 
}

function TestSQLite()
{
$dsn = 'sqlite:./test/mydb.sq3';
$user = '';
$password = '';

try {
   $dbh = new PDO($dsn, $user, $password);
/*   $sql ="create table if not exists genres (
  genreId int autoincrement primary key,
  genre varchar(255),
  unique (genre)
)";
*/
   $sql ="CREATE TABLE IF NOT EXISTS genres(genreId int, genre varchar(255))";
   $dbh->query($sql);
/*   $dbh->query("insert into genres (genre) values ('blues');
insert into genres (genre) values ('classical');
insert into genres (genre) values ('country');
insert into genres (genre) values ('data');
insert into genres (genre) values ('folk');
insert into genres (genre) values ('jazz');
insert into genres (genre) values ('misc');
insert into genres (genre) values ('newage');
insert into genres (genre) values ('reggae');
insert into genres (genre) values ('rock');
insert into genres (genre) values ('soundtrack');
");
   foreach ($dbh->query('select genre from genres') as $row) {
       print $row['genre'] . "<br>";
   }       
*/  
}catch (PDOException $e) {
   echo 'Connection failed: ' . $e->getMessage();
}


 //phpinfo();
/* $file = "./test/freedb-update-20060501-20060601.tar.bz2";
 $bz = bzopen($file, "r") or die("Couldn't open $file for reading");
 bzclose($bz); */
}

 //set_time_limit(1000);
 //TestSQLite();
 phpinfo();
 
?>