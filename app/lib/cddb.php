<? 
//=====================================================================
//==   PHP Implementaion of CDDB Protocol (HTTP Interface only)      ==
//==      Current implemented Protocol = 5  (with some exceptions)   ==
//==                                                                 ==
//==        (c) 2006 Massimo Magnano (max.maxm@tiscali.it)           ==
//==    This is a free script, you can free use it, see cddb license ==
//=====================================================================

// Fill the variables below with your configurations data

 //Version of this Script
 $VERSION = "0.0.8"; 
 //Server Name
 $SERVER_HOST_NAME = "sortinonline.it";
 
 //Sites Info
 $Sites = array(/*"localhost/sortinonline.it/cddb/cddb.php 80 N000.00 W000.00 LocalSite"*/);
 
 //CDDB Access File Location
 $CDDB_ACCESS_FILE = "access.hdr";
  
 //CDDB is in Windows Format? (true)
 $DB_WINDOWS_FORMAT = true;
 
 //Always Search in both exact and inexact matches (false)
 $QUERY_ALWAYS_FUZZY =false;
 
 //Be more restrictive checking the Track Num and Offsets in Exact Matches (true)
 $QUERY_CHECK_TRACKNUM =true;
 
 //Respond to query_dtitle extension (experimental) (false)
 $PROTO_QUERY_DTITLE =true;
 
 //Timeout of this Script (1000)
 $SCRIPT_TIMEOUT =1000; //seconds, in your server may be different (set also the CGI Timeout in Web Server)
 
 //Show Debug Info (false)
 $Debug =false;
 
 //Log Variables
 $DO_LOG =true;
 $LOG_FILE ="./log/cddb.log";
 
//-------end of configurations data----------------------
  
 $IF_SUBMIT =1;
 $IF_EMAIL  =2;
 $IF_CDDBP  =4;
 $IF_HTTP   =8;
 $IF_ALL    =15;
 $CDDBFRAMEPERSEC = 75;
 
 //Constants below are predefined values if not found in access.hdr file
 $CDDBDIR ="";
 $WORKDIR ="";
 $FUZZY_FACTOR = 900;
 $FUZZY_DIV = 4;
 $FUZZY_INDEX_DIV = 50;

 //Global Variables
 $CMDLINE = array();
  
 
function put_log($filename, $data, $file_append = false) {
  $fp = fopen($filename, (!$file_append ? "w+" : "a+"));
  if(!$fp) {
    trigger_error("put_log cannot write in file.", E_USER_ERROR);
    return;
  }
  fwrite($fp, $data);
  fclose($fp);
}

if(!function_exists("scandir")) {
function scandir($dir) {
  $dh  = opendir($dir);
  while (false !== ($filename = readdir($dh))) {
    $files[] = $filename;
  }
  closedir($dh);
  return $files;
}
}

function microtime_float() 
{ 
   list($usec, $sec) = explode(" ", microtime()); 
   return ((float)$usec + (float)$sec); 
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

function parse_iface($iface, &$is_not)
{
 global $IF_SUBMIT, $IF_EMAIL, $IF_CDDBP, $IF_HTTP, $IF_ALL;

 $is_not = ($iface{0} == '-');
 $result = 0;
 for ($i = 0; $i <= strlen($iface)-1; $i++) {
   switch ($iface{$i}) {
   case 'C':
     $result |=$IF_CDDBP;
     break;
   case 'H':
     $result |=$IF_HTTP;
     break;
   case 'E':
     $result |=$IF_EMAIL;
     break;
   case 'S':
     $result |=$IF_SUBMIT;
     break;
   }
 }
 return $result; 
}

function CheckHello()
{
//-> cddb hello username hostname clientname version
 global $CDDB_ACCESS_FILE, $IF_SUBMIT, $IF_EMAIL, $IF_CDDBP, $IF_HTTP, $IF_ALL;
 global $CMDLINE;

 if (!isset($CMDLINE['hello'])) die("500 Hello Command not present.\r\n");
  
 $HelloCmd = explode(" ", $CMDLINE['hello']);
 if (count($HelloCmd) != 4) die( "500 Command syntax error: incorrect arg count for handshake.\r\n");
   
 $username = strtoupper($HelloCmd[0]);
 $clientname = strtoupper($HelloCmd[2]);
 $hostname = strtoupper($HelloCmd[1]);
 $version = strtoupper($HelloCmd[3]);
 $return = true;
 
 $file = fopen($CDDB_ACCESS_FILE, "r");
 while (!feof($file)) {
   $line = fgets($file);
   if ((!is_null($line)) && ($line != "") && ($line{0} != '#')) {
     $Uline = strtoupper($line);
     $npos = strpos($Uline, "CLIENT_PERMS:");
     if ($npos !== false) {
       $permsStr = trim(substr($Uline, $npos+13));
       $perms = explode(" ", $permsStr); 
       if (($clientname == $perms[2]) || ($perms[2] == "-")) { //All Clients or ThisClient
         $iface_not = false;
         $iface = parse_iface($perms[0], $iface_not);
         if (($iface & $IF_HTTP) > 0) {
           if ($perms[1] == "ALLOW") { $return = !($iface_not); } else { $return = $iface_not; }
             //if ($return) check of versions...
         }
       }
     }
     else {
       /*$npos = strpos($Uline, "host_perms:");
       if ($npos !== false) { //(!(is_null($permsStr)))
         echo "host_perms:\n";
         $permsStr = trim(substr($Uline, $npos+12));
         $perms = SplitCmd($permsStr);
         echo " host_permsStr =".$permsStr."\n";
       }
       else {*/ 
         $npos = strpos($Uline, "CDDBDIR:");
         if ($npos !== false) {
           global $CDDBDIR;
           $CDDBDIR = trim(substr($line, $npos+8));
           if ($CDDBDIR{strlen($CDDBDIR)-1} != '/') $CDDBDIR .='/';
           if (!is_dir($CDDBDIR)) die("500 Database Path Error ($CDDBDIR)\r\n");
         }
         else {
           $npos = strpos($Uline, "WORKDIR:");
           if ($npos !== false) {
             global $WORKDIR;
             $WORKDIR = trim(substr($line, $npos+8));
             if ($WORKDIR{strlen($WORKDIR)-1} != '/') $WORKDIR .='/';
             if (!is_dir($WORKDIR)) die("500 Server Work Path Error ($WORKDIR)\r\n");
           }
           else {
             $npos = strpos($Uline, "FUZZY_FACTOR:");
             if ($npos !== false) {
               global $FUZZY_FACTOR;
               $FUZZY_FACTOR = (int)(trim(substr($line, $npos+13)));
             }
             else {
               $npos = strpos($Uline, "FUZZY_DIV:");
               if ($npos !== false) {
                 global $FUZZY_DIV;
                 $FUZZY_DIV = (int)(trim(substr($line, $npos+10)));
               }
             }
           }
         }
       //} host perms part...
     }
   }//line not null
 }//while
 fclose($file);
 return $return;
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

function GetWinDBFileName($Dir, $DiscID)
{
 sscanf(substr($DiscID, 0, 2), "%02x", $DiscID_High);
 $dh  = opendir($Dir);
 while (false !== ($filename = readdir($dh))) {
   $start =0;
   $end =0;
   if (sscanf(strtolower($filename), "%02xto%02x", $start, $end) == 2) {
     if (($DiscID_High >= $start) && ($DiscID_High <= $end)) {
      closedir($dh);
      return $Dir.$filename;
     }
   }
 }
 closedir($dh);

 return NULL;
}

function GetDisclen($line)
{
//"$Disclen $DiscID $DiscID_fpos/Tracks..."
  $dummy ="";
  $Disclen =0;
  sscanf($line, "%d %s", $Disclen, $dummy);
  return $Disclen;
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

function GetDTitle($file)
{
 //search first DTITLE
 $dpos =false;
 do {
     if (feof($file)) return false;
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
 return $DTitle;
}           


function do_query_fuzzy($Cmd, $EchoCode, $DiscID, $NTracks, $TracksOffset, $TotalSec)
{
 global $Debug, $CDDBDIR, $DB_WINDOWS_FORMAT, $WORKDIR, $FUZZY_FACTOR, $FUZZY_DIV, $CDDBFRAMEPERSEC, $FUZZY_INDEX_DIV;
 
if ($Debug) $time_startx = microtime_float();
if ($Debug) $T0 = 0;

 $InexactMatch = array();
 $Categories = scandir($CDDBDIR);
   
 // Compute the max tolerance for the CD length. */
 $MaxCDLength = $FUZZY_FACTOR * ($NTracks + 1) / $FUZZY_DIV / $CDDBFRAMEPERSEC;
 if ($MaxCDLength < 1) $MaxCDLength = 1;
 
 for ($i = 0; $i < count($Categories); $i++) {
   $Category = strtolower($Categories[$i]);
   if (is_dir($CDDBDIR.$Category) && ($Category{0} != '.') && file_exists($WORKDIR."INDEXES/FUZZY/".$Category."/".$NTracks)) {
     $file = fopen($WORKDIR."INDEXES/FUZZY/".$Category."/".$NTracks, "r");

     //Seek to Index Position
     $iIndexStr = fgets($file);
     $Indexes = explode(" ", $iIndexStr);
     $FirstOfs = strlen($iIndexStr);
     $Index = floor(($TotalSec-$MaxCDLength) / $FUZZY_INDEX_DIV);
     if ($Index>count($Indexes)-1) $Index = count($Indexes)-1;
     fseek($file, $Indexes[$Index]+$FirstOfs);

     while (!feof($file)) {
         $line = fgets($file);
         $myDisclen =0;
         $myDiscId ="";
         $dummy ="";

         //1 sscanf(substr($line, $npos+1), "%d %s %s", $myDisclen, $myDiscId, $dummy);
         sscanf($line, "%d %s %d/%s", $myDisclen, $myDiscId, $myDiscId_fpos, $dummy);

         //Check to see if length is within tolerance.
         $j = $myDisclen - $TotalSec;

         // All entries beyond this point are not matches.
         if ($j > $MaxCDLength) break;

         // Find abs.
         if ($j < 0) $j *= -1;

         // Not a match if the allowable diff is < than the actual. 
         if ($j > $MaxCDLength) continue;

         //Get Track Offsets
         $myTracksOffsets =GetTracksOfs($line);

         if (is_fuzzy_match($TracksOffset, $myTracksOffsets, $TotalSec, $myDisclen, $NTracks)) {
//Get DTitle
if ($Debug) $time0 = microtime_float();
    if ($DB_WINDOWS_FORMAT) { $file_name = GetWinDBFileName($CDDBDIR.$Category."/", $myDiscId); }
    else { $file_name = $CDDBDIR.$Category."/".$myDiscId; }
    if (!is_readable($file_name)) die("403 Database entry is corrupt. ($file_name)\r\n");
    $fileD = fopen($file_name, "r");
    fseek($fileD, $myDiscId_fpos); 
    $line = fgets($fileD);
    if (feof($fileD)) die("403 Database entry is corrupt. (DiscID=$myDiscId)\r\n");
    $Uline =strtoupper($line);
    $npos = strpos($Uline, "#FILENAME=");
    if ($npos !== false) {
      if (strtolower(trim(substr($line, $npos+10))) == $myDiscId) {
       $myDTitle =GetDTitle($fileD);
       if ($myDTitle === false) die("403 Database entry is corrupt. (DiscID=$myDiscId)\r\n");
           
       $InexactMatch[] = "$Category $myDiscId $myDTitle"; 
      }
      else die("403 Fuzzy Index is corrupt. ($Category/$NTracks)\r\n");
    }
    else die("403 Fuzzy Index is corrupt. ($Category/$NTracks)\r\n");
    fclose($fileD);
if ($Debug) { $time2 =microtime_float(); $T0 +=$time2-$time0; }

         }//Is Fuzzy Match
     }//while feof
     fclose($file);
   }//Is valid category
 }//For Categories
 
 $count_InexactMatch = count($InexactMatch);
 if ($count_InexactMatch > 0) {
  if ($EchoCode) echo "211 Found Inexact matches, list follows (until terminating `.')\r\n";
  for ($i = 0; $i<$count_InexactMatch; $i++) {
   echo $InexactMatch[$i]."\r\n";
  }
  if ($EchoCode) echo ".\r\n";
 }
 else { if ($EchoCode) echo("202 No match found\r\n"); }
 
if ($Debug) echo "T0 = $T0<br>";
if ($Debug) echo " do_query_fuzzy Time = ".(microtime_float()-$time_startx)."<br>";
 return $count_InexactMatch;
}

 //Ver new
function GetTracksLengths($file, &$TrackOffsets, &$Disclen) {
  $endOffsets = false;
  while (!$endOffsets) {
    $line = fgets($file);
    if ($line{0}!='#') break;

    $Uline = strtoupper($line);
    //In some cases no div lines between Track Frames and Disc length (blues/7912cb19)
    $npos = strpos($Uline, "DISC LENGTH:");
    if ($npos !== false) { 
      $spos = strpos($Uline, "SECOND");
      if ($spos === false) { $Disclen = (int)(trim(substr($line, $npos+12))); }
      else { $Disclen = (int)(trim(substr($line, $npos+12, ($spos-$npos-12)))); }

      break;
    }
    $endOffsets = (strlen($line)==2);
    if (!$endOffsets) { 
      $curTrack = (int)(trim(substr($line, 1)));
      $endOffsets = ($curTrack<1);
      if (!$endOffsets) { $TrackOffsets[] .=$curTrack; }
    }
  }//while TRACK FRAME OFFSETS ends

  if ($Disclen == 0) {
    $endOffsets = false;
    while (!$endOffsets) {
      $line = fgets($file);
      if ($line{0}!='#') break;

      $Uline = strtoupper($line);
      $npos = strpos($Uline, "DISC LENGTH:");
      $endOffsets = ($npos !== false);
      if ($endOffsets)  {
        $spos = strpos($Uline, "SECOND");
        if ($spos === false) { $Disclen = (int)(trim(substr($line, $npos+12))); }
        else { $Disclen = (int)(trim(substr($line, $npos+12, ($spos-$npos-12)))); }
      }
    }//while DISC LENGTH ends
  }
}
/*
function GetTracksLengths($file, &$TrackOffsets, &$Disclen) {
  $endOffsets = false;
  while (!$endOffsets) {
    $line = fgets($file);
    $Uline = strtoupper($line);

    //In some cases no div lines between Track Frames and Disc length (7912cb19)
    $npos = strpos($Uline, "DISC LENGTH:");
    if ($npos !== false) { 
      $spos = strpos($Uline, "SECOND");
      if ($spos === false) { $Disclen = (int)(trim(substr($line, $npos+12))); }
      else { $Disclen = (int)(trim(substr($line, $npos+12, ($spos-$npos-12)))); }

      break;
    }
    $endOffsets = (strlen($line)==2);
    if (!$endOffsets) { 
      $curTrack = (int)(trim(substr($line, 1)));
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
        if ($spos === false) { $Disclen = (int)(trim(substr($line, $npos+12))); }
        else { $Disclen = (int)(trim(substr($line, $npos+12, ($spos-$npos-12)))); }
             }
    }//while DISC LENGTH ends
  }
}
*/
 
function do_query($Cmd) 
{
//-> cddb query discid ntrks off1 off2 ... nsecs
//<- code categ discid dtitle
//<- (more matches...)
//<- .

 if (count($Cmd) < 6) die("500 Query Command syntax error\r\n");

 global $Debug, $CDDBDIR, $DB_WINDOWS_FORMAT, $WORKDIR, $QUERY_ALWAYS_FUZZY, $QUERY_CHECK_TRACKNUM;

if ($Debug) $time0 =microtime_float();


 $DiscID = strtolower($Cmd[2]);
 $NTracks = $Cmd[3];
 if (count($Cmd) != (5+$NTracks)) die("500 Query Command syntax error\r\n");
 
 $TracksOffset = array_slice($Cmd, 4, $NTracks);
 $TotalSec = $Cmd[4+$NTracks];
 $ExactMatch = array();
 $Categories = scandir($CDDBDIR);
    
 $Index_filename =substr($DiscID, 0, 2);
 sscanf(substr($DiscID, 2, 2), "%02x", $Index);
 $DiscID_d =hexdec($DiscID);
 
 for ($i = 0; $i < count($Categories); $i++) {
   $Category = strtolower($Categories[$i]);
   if (is_dir($CDDBDIR.$Category) && ($Category{0} != '.')) {

    if ($DB_WINDOWS_FORMAT) { $file_name = GetWinDBFileName($CDDBDIR.$Category."/", $DiscID); }
    else { $file_name = $CDDBDIR.$Category."/".$DiscID; }
    if (!is_readable($file_name)) die("403 Database entry is corrupt. ($file_name)\r\n");
    $file = fopen($file_name, "r");

    //Try to seek to DiscID index
    $IsIndexed = file_exists($WORKDIR."INDEXES/DISCID/".$Category."/".$Index_filename);
if ($Debug) echo "Category=$Category IsIndexed = $IsIndexed<br>";
    if ($IsIndexed) {
     $fileIndex = fopen($WORKDIR."INDEXES/DISCID/".$Category."/".$Index_filename, "r");
     $iIndexStr = fgets($fileIndex);
     $Indexes = explode(" ", $iIndexStr);
     $FirstOfs = strlen($iIndexStr);
     if ($Index>count($Indexes)-1) $Index = count($Indexes)-1;
     fseek($fileIndex, $Indexes[$Index]+$FirstOfs);
    }
    $SearchDiscID = true;
    while (!feof($file)) {
      if ($IsIndexed) {
        do {
            $lineIndex = fgets($fileIndex);
            if (feof($fileIndex)) $IndexDiscID_d =4294967295; 
            else { sscanf($lineIndex, "%s %d", $IndexDiscID, $FilePos); $IndexDiscID_d = hexdec($IndexDiscID); }
        } while ($IndexDiscID_d < $DiscID_d);
        if ($IndexDiscID_d > $DiscID_d) break;
        fseek($file, $FilePos);
if ($Debug) echo "lineIndex = $lineIndex, $IndexDiscID, $FilePos<br>";
      }
      $line = fgets($file);
if ($Debug) echo "line = $line<br>";
      if ($line{0}!='#') continue; //I'm here only if i search for #FILENAME

      $Uline =strtoupper($line);

      $npos = strpos($Uline, "#FILENAME=");
      if ($npos !== false) {
        if (strtolower(trim(substr($line, $npos+10))) == $DiscID) {//My DiscID
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
  
            $AddThis = (is_fuzzy_match($TracksOffset, $myTrackOffsets, $TotalSec, $myDisclen, $NTracks));
          }//QUERY_CHECK_TRACKNUM

          if ($AddThis) {
            $DTitle = GetDTitle($file);
            if ($DTitle === false) die("403 Database entry is corrupt. (DiscID=$DiscID)\r\n"); 

            if (!is_null($DTitle)) { 
              $ExactMatch[] = "$Category $DiscID $DTitle"; 
              break;//thake only the first, i cannot read more than one in "cddb read categ discid"
            } 
          }
        }//My DiscID
      }//Another DiscID
    }//while feof($file)
    fclose($file);

    if ($IsIndexed) fclose($fileIndex);
        
   }//is valid category 
 }//for categories
 
if ($Debug) echo "time query =".(microtime_float()-$time0)."<br>";

 $count_ExactMatch = count($ExactMatch);
 if ($count_ExactMatch > 1) {
   echo "210 Found Exact matches, list follows (until terminating `.')\r\n";
   for ($i = 0; $i<$count_ExactMatch; $i++) {
     echo $ExactMatch[$i]."\r\n";
   }
 }
 else {
   if ($count_ExactMatch == 1) {
     if (!$QUERY_ALWAYS_FUZZY) { echo "200 ".$ExactMatch[0]."\r\n"; return; }
     else { echo "210 Found Exact matches, list follows (until terminating `.')\r\n".$ExactMatch[0]."\r\n"; }
   }
   else { 
     do_query_fuzzy($Cmd, true, $DiscID, $NTracks, $TracksOffset, $TotalSec);
     return;
   }
 }
 
 if ($QUERY_ALWAYS_FUZZY) do_query_fuzzy($Cmd, false, $DiscID, $NTracks, $TracksOffset, $TotalSec);
 echo ".\r\n";
}

function do_read($Cmd) 
{
//-> cddb read categ discid
//<- code categ discid 
//<- (CDDB data...)
//<- .  

 if (count($Cmd) < 4) die("500 Read Command syntax error\r\n");

 global $Debug, $CDDBDIR, $WORKDIR, $DB_WINDOWS_FORMAT;
 define("E_NOT_FOUND", "401 Specified CDDB entry not found\r\n");
 $Category = $Cmd[2];
 $DiscID = strtolower($Cmd[3]);

 if (is_dir($CDDBDIR.$Category) && ($Category{0} != '.')) {

   $Index_filename =substr($DiscID, 0, 2);
   sscanf(substr($DiscID, 2, 2), "%02x", $Index);
   $DiscID_d =hexdec($DiscID);
 
   if ($DB_WINDOWS_FORMAT) { $file_name = GetWinDBFileName($CDDBDIR.$Category."/", $DiscID); }
   else { $file_name = $CDDBDIR.$Category."/".$DiscID; }
   if (!is_readable($file_name)) die(E_NOT_FOUND);
   $file = fopen($file_name, "r");

   //Try to seek to DiscID index
   $IsIndexed = file_exists($WORKDIR."INDEXES/DISCID/".$Category."/".$Index_filename);
if ($Debug) echo "Category=$Category IsIndexed = $IsIndexed<br>";
   if ($IsIndexed) {
     $fileIndex = fopen($WORKDIR."INDEXES/DISCID/".$Category."/".$Index_filename, "r");
     $iIndexStr = fgets($fileIndex);
     $Indexes = explode(" ", $iIndexStr);
     $FirstOfs = strlen($iIndexStr);
     if ($Index>count($Indexes)-1) $Index = count($Indexes)-1;
     fseek($fileIndex, $Indexes[$Index]+$FirstOfs);
   }

   $CopyData = false;
   $Result = array();
   while (!feof($file)) {
      if ((!$CopyData) && ($IsIndexed)) {
        do {
            $lineIndex = fgets($fileIndex);
            if (feof($fileIndex)) $IndexDiscID_d =4294967295; 
            else { sscanf($lineIndex, "%s %d", $IndexDiscID, $FilePos); $IndexDiscID_d = hexdec($IndexDiscID); }
        } while ($IndexDiscID_d < $DiscID_d);
        if ($IndexDiscID_d > $DiscID_d) break;
        fseek($file, $FilePos);
if ($Debug) echo "lineIndex = $lineIndex, $IndexDiscID, $FilePos<br>";
      }
      $line = fgets($file);
      if ((!$CopyData) && ($line{0}!='#')) continue;

      $Uline =strtoupper($line);

      $npos = strpos($Uline, "#FILENAME=");
      if ($npos !== false) {
        if ($CopyData) { break; }
        else { $CopyData = (strtolower(trim(substr($line, $npos+10))) == $DiscID); }
      }

      if ($CopyData) { $Result[] = $line; }  
   }//while
   fclose($file);

   if (count($Result)>0) {
     echo "210 $Category $DiscID\r\n";
     for ($i = 0; $i<count($Result); $i++) {
       echo $Result[$i]."\r\n";
     }
     echo ".\r\n";
   }
   else {
     if ($CopyData) { die("403 Database entry is corrupt.\r\n"); }
     else { die(E_NOT_FOUND); }
   }
 }//is valid category
 else { die(E_NOT_FOUND); } 
}

function do_lscat($Cmd)
{
//-> cddb lscat
//<- code Okay category list follows (until terminating marker)
//<- category
//<- (more categories...)
//<- .
 
  global $CDDBDIR;
  $Categories = scandir($CDDBDIR);
  if (count($Categories) == 0) die("500 Server Error\r\n");
 
  echo "201\r\n";
  for ($i = 0; $i < count($Categories); $i++) {
    $Category = $Categories[$i]; 
    if (is_dir($CDDBDIR.$Category) && ($Category{0} != '.')) {
      echo "$Category\r\n"; 
    }
  } 
  echo ".\r\n";
}

function do_sites($Cmd)
{
//-> cddb lscat
//<- code Okay category list follows (until terminating marker)
//<- category
//<- (more categories...)
//<- .
  global $Sites;
  if (count($Sites) == 0) die("401 No site information available\r\n");
 
  $SitesStr ="";
  for ($i = 0; $i < count($Sites); $i++) {
  	$SitesStr .=$Sites[$i]."\r\n";
  } 
  echo "201 OK, site information follows (until terminating `.')\r\n$SitesStr.\r\n";
}



function FindDTitle_noindex($Category, $Artist, $Title, &$ExactMatch)
{
 global $CDDBDIR;
 $DiscID ="";

 if (is_dir($CDDBDIR.$Category) && ($Category{0} != '.')) {
  $files = scandir($CDDBDIR.$Category);
  for ($k = 0; $k < count($files); $k++) {
   $file_name = $files[$k];
   if (($file_name{0} == '.') || (!is_readable($CDDBDIR.$Category."/".$file_name))) continue;
   $file = fopen($CDDBDIR.$Category."/".$file_name, "r");

    $SearchDiscID = true;
    while (!feof($file)) {
      $line = fgets($file);
      if ($SearchDiscID) { $npos = strpos($line, "#FILENAME="); }
      else { $npos = strpos($line, "DTITLE="); }
      if ($npos !== false) {
        if ($SearchDiscID) {
          $DiscID = strtolower(trim(substr($line, $npos+10)));
          $SearchDiscID = false;
        }
        else { 
          $SearchDiscID =true;
          $DTitle = trim(substr($line, $npos+7));
          if (!is_null($DTitle)) {
            $curTitle = "";
            $curArtist = "";
            $tpos = strpos($DTitle, "/");
            if ($tpos !== false) { 
              $curTitle =strtoupper(trim(substr($DTitle, $tpos+1)));
              $curArtist =strtoupper(trim(substr($DTitle, 0, $tpos-1))); 
            }
            else { $curArtist =strtoupper($DTitle); }
            $AddThis =true;
            if ($Artist != "*") { $AddThis = ($Artist == $curArtist); }
            if ($Title != "*") { $AddThis = ($Title == $curTitle); }

            if ($AddThis) { $ExactMatch[] = "$Category $DiscID $DTitle"; }
          } 
        } 
      }//($npos !== false)
    }//while
    fclose($file);
  }//for files
 }//is valid category 
 else die("500 Query DTitle Category error\r\n");
}

function do_query_dtitle($Cmd) 
{
//-> cddb query_dtitle categ / artist / title 
//<- code categ discid dtitle
//<- (more matches...)
//<- .

 if (count($Cmd) < 3) die("500 Query DTitle Command syntax error\r\n");
 global $CDDBDIR, $SCRIPT_TIMEOUT, $CMDLINE;
 
 $Command = strtoupper($CMDLINE['cmd']);
 $cpos = strpos($Command, "QUERY_DTITLE ");
 if ($cpos !== false) {
   $CategArtistTitle =  substr($Command, $cpos+13);
   $tpos = strpos($CategArtistTitle, "/");
   if ($tpos !== false) { 
     $Category =trim(substr($CategArtistTitle, 0, $tpos-1)); 
     $ArtistTitle =trim(substr($CategArtistTitle, $tpos+1));
     $tpos = strpos($ArtistTitle, "/");
     if ($tpos !== false) { 
       $Artist =trim(substr($ArtistTitle, 0, $tpos-1)); 
       $Title =trim(substr($ArtistTitle, $tpos+1));
     }
     else {
       $Artist =trim($ArtistTitle);
       $Title = "*";
     }
   }
   else {
     $Category = "*";
     $Artist =trim($CategArtistTitle);
     $Title = "*";
   }
 } 
 else die("500 Query DTitle Command syntax error\r\n");

 $ExactMatch = array();
 $Categories = scandir($CDDBDIR);
 
//$time_startx = microtime_float();

 if ($Category == "*") { 
   for ($i = 0; $i < count($Categories); $i++) { FindDTitle_noindex(strtolower($Categories[$i]), $Artist, $Title, $ExactMatch); } 
 }
 else { FindDTitle_noindex($Category, $Artist, $Title, $ExactMatch); }  

//echo " FindDTitle Time = ".(microtime_float()-$time_startx)."<br>";

 if (count($ExactMatch) > 1) {
  echo "210 ".$ExactMatch[0]."\r\n";
  for ($i = 0; $i<count($ExactMatch); $i++) {
    echo $ExactMatch[$i]."\r\n";
  }
  echo ".\r\n";
 }
 else {
   if (count($ExactMatch) == 1) echo "200 ".$ExactMatch[0]."\r\n";
 } 
}

function GetCommandLine() {
  global $CMDLINE;
  if (isset($_SERVER['REQUEST_METHOD'])) { 
    if ($_SERVER['REQUEST_METHOD'] == "GET") { $CMDLINE = $_GET; }
    else { 
      if ($_SERVER['REQUEST_METHOD'] == "POST") { $CMDLINE = $_POST; }
      else die("500 No Command Line.\r\n");
    }
  }
  else { 
    for ($i=0; $i<count($_SERVER['argv']); $i++) {
      $Arg =$_SERVER['argv'][$i];
      $UArg = strtoupper($Arg);
      $npos = strpos($UArg, "=");
      if ($npos !== false) { $CMDLINE[strtolower(substr($Arg, 0, $npos))] = substr($Arg, $npos+1); }
    }
  }  
}

$time_startx = microtime_float();

//Main...
GetCommandLine();
if (CheckHello())
{
 set_time_limit($SCRIPT_TIMEOUT);
 if (!isset($CMDLINE['cmd'])) die("201 $SERVER_HOST_NAME CDDBP server $VERSION.php ready at ".date("D M d H:i:s Y")."\r\n");
 
 if ($DO_LOG) {
   $LogStr =$CMDLINE['cmd'];
   for ($i=0; $i<strlen($LogStr); $i++) if ($LogStr{$i}==' ') $LogStr{$i} ='+';
   put_log($LOG_FILE, "-------------------- \r\n cmd=$LogStr\r\n", true);
 }

 $Cmd = explode(" ", $CMDLINE['cmd']);
 if (count($Cmd)==0) die("401 No Command\r\n");
 if (count($Cmd)>1) {
   if (strtolower($Cmd[0]) == "cddb") {
     if (strtolower($Cmd[1]) == "query") { do_query($Cmd); } 
     elseif (strtolower($Cmd[1]) == "read") { do_read($Cmd); }
     elseif (strtolower($Cmd[1]) == "lscat") { do_lscat($Cmd); }
     elseif (strtolower($Cmd[1]) == "query_dtitle") { if ($PROTO_QUERY_DTITLE) do_query_dtitle($Cmd); }
   }
 }
 else {
 	if (strtolower($Cmd[0]) == "sites") { do_sites($Cmd); }
 }
}
else {
  if ($DO_LOG) {
   $LogStr =$CMDLINE['hello'];
   for ($i=0; $i<strlen($LogStr); $i++) if ($LogStr{$i}==' ') $LogStr{$i} ='+';
   put_log($LOG_FILE, "-------------------- \r\n Handshake not successful hello=$LogStr\r\n", true);
  }
  die("431 Handshake not successful, closing connection\r\n");
}

$timer = (microtime_float()-$time_startx);  
if ($DO_LOG) put_log($LOG_FILE, "-------------------- time=$timer seconds\r\n\r\n", true);
if ($Debug) echo "<br>Total Time=$timer seconds";
?>
