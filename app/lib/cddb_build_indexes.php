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
 
 //CDDB Access File Location
 $CDDB_ACCESS_FILE = "access.hdr";
  
 //CDDB is in Windows Format?
 $DB_WINDOWS_FORMAT = true;
 
 $SCRIPT_TIMEOUT =10000; //seconds, in your server may be different (set also the CGI Timeout in Web Server)
 
 //Show Debug Info (false)
 $Debug =false;
 
 //Log Variables
 $DO_LOG =true;
 $LOG_FILE ="./log/cddb_build_indexes.log";

//-------end of configurations data----------------------
  
 $IF_SUBMIT =1;
 $IF_EMAIL  =2;
 $IF_CDDBP  =4;
 $IF_HTTP   =8;
 $IF_ALL    =15;
 $CDDBFRAMEPERSEC = 75;
 $TYPE_NONE  =0;
 $TYPE_FUZZY =1;
 $TYPE_DISCID =2;
 $TYPE_DTITLE =4;
 
 //Constants below are predefined values if not found in access.hdr file
 $CDDBDIR ="";
 $WORKDIR ="";
 $FUZZY_FACTOR = 900;
 $FUZZY_DIV = 4;
 $FUZZY_INDEX_DIV = 50;
 
 //Global Variables
 $CMDLINE = array();
 $CurCategory ="";
 $DiscID_Collisions = array();
  
 
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

function my_file($filename) {
 $Lines =array();
 $file =fopen($filename, "r");
 while (true) {
   $line = fgets($file);
   if (feof($file)) break;
   else $Lines[] = $line;
 }
 fclose($file);
 return $Lines;
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
  
 //$HelloCmd = SplitCmd($CMDLINE['hello']);
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
       //$perms = SplitCmd($permsStr);
       $perms = explode(" ", $permsStr);
       if (($clientname == $perms[2]) || ($perms[2] == "-")) { //All Clients or ThisClient
         $iface_not = false;
         $iface = parse_iface($perms[0], $iface_not);
         if ( (($iface & $IF_HTTP) > 0) && (($iface & $IF_CDDBP) > 0)) {
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


function GetDisclen($line)
{
//"$Disclen $DiscID $DiscID_fpos/Tracks..."
  $dummy ="";
  $Disclen =0;
  sscanf($line, "%d %s", $Disclen, $dummy);
  return $Disclen;
}


/*1
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
*/

function GetDiscID_fpos($line)
{
//"$Disclen $DiscID $DiscID_fpos/Tracks..."
  $dummy ="";
  $DiscId_fpos =0;
  sscanf($line, "%d %s %d/%s", $dummyDisclen, $dummy, $DiscId_fpos, $dummy);
  return $Disclen;
}

function cmpByDiscLen($a, $b) 
{
 $a_dlen = GetDisclen($a);
 $b_dlen = GetDisclen($b);
   
 if ($a_dlen == $b_dlen) { return 0; }
 return ($a_dlen < $b_dlen) ? -1 : 1;
}

function cmpByDiscID($a, $b) 
{
 sscanf($a, "%s %d", $a_s, $a_pos); $a_id =hexdec($a_s); //Php 4.3.1 bug 800a8b0a == 8005ef0a
 sscanf($b, "%s %d", $b_s, $b_pos); $b_id =hexdec($b_s);
   
 if ($a_id == $b_id) {
   global $CurCategory, $DiscID_Collisions, $DO_LOG, $LOG_FILE;
   if (!isset($DiscID_Collisions[$CurCategory][$a_id])) { 
     $DiscID_Collisions[$CurCategory][$a_id] =true;
     $DiscID =dechex($a_id);
     echo("\r\nWARNING ($CurCategory/$DiscID) Collision $a_pos $b_pos\r\n");
     if ($DO_LOG) put_log($LOG_FILE, "\r\nWARNING ($CurCategory/$DiscID) Collision $a_pos $b_pos\r\n", true);
   }
   if ($a_pos == $b_pos) return 0; 
   else return ($a_pos < $b_pos) ? -1 : 1;
 }
 return ($a_id < $b_id) ? -1 : 1;
}

function sort_index($type, $Category)
{
 global $WORKDIR, $CDDBDIR, $FUZZY_INDEX_DIV, $TYPE_FUZZY, $TYPE_DISCID, $TYPE_DTITLE;

 if ($type & $TYPE_FUZZY) {
   echo("\r\nSorting Fuzzy Category --> $Category...\r\n");

   $files = scandir($WORKDIR."INDEXES/FUZZY/".$Category);
   for ($k = 0; $k < count($files); $k++) {
     $file_name = $files[$k];
     if (($file_name{0} == '.') || (!is_readable($WORKDIR."INDEXES/FUZZY/".$Category."/".$file_name))) continue;

     echo("$file_name, ");
     $Lines = my_file($WORKDIR."INDEXES/FUZZY/".$Category."/".$file_name);
     usort($Lines, "cmpByDiscLen");
   
     $IndexByDisclen =array();
     $file = fopen($WORKDIR."INDEXES/FUZZY/".$Category."/".$file_name, "w");

     $filepos = 0;
     for ($i =0; $i<count($Lines); $i++) {
       $line =$Lines[$i];
       $Disclen =GetDisclen($line);
       $ipos = floor($Disclen / $FUZZY_INDEX_DIV);
       if (!isset($IndexByDisclen[$ipos])) $IndexByDisclen[$ipos] = $filepos;
       $filepos +=strlen($line);
     }
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
   } 
   echo("\r\nSorting Fuzzy Category --> $Category DONE\r\n");
  }

 if ($type & $TYPE_DISCID) {
   echo("\r\nSorting DiscID Category --> $Category...\r\n");

   $files = scandir($WORKDIR."INDEXES/DISCID/".$Category);
   for ($k = 0; $k < count($files); $k++) {
     $file_name = $files[$k];
     if (($file_name{0} == '.') || (!is_readable($WORKDIR."INDEXES/DISCID/".$Category."/".$file_name))) continue;

     echo("$file_name, ");
     $Lines = my_file($WORKDIR."INDEXES/DISCID/".$Category."/".$file_name);
     usort($Lines, "cmpByDiscID");
   
     $IndexBy23 =array();
     $file = fopen($WORKDIR."INDEXES/DISCID/".$Category."/".$file_name, "w");

     $filepos = 0;
     for ($i =0; $i<count($Lines); $i++) {
       $line =$Lines[$i];
       sscanf(substr($line, 2, 2), "%02x", $ipos);
       if (!isset($IndexBy23[$ipos])) $IndexBy23[$ipos] = $filepos;
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
   } 
   echo("\r\nSorting DiscID Category --> $Category DONE\r\n");
  }


} 

 //Ver new
function GetTracksLengths($file, &$TrackOffsets, &$Disclen) {
//$time0 =microtime_float();

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

//echo "time GetTracksLengths =".(microtime_float()-$time0)."<br>";
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
function build_index($type, $Category)
{
 global $WORKDIR, $CDDBDIR, $TYPE_FUZZY, $TYPE_DISCID, $TYPE_DTITLE, $DO_LOG, $LOG_FILE;
 $DiscID ="";
 $Disclen = 0;
 $prevWarning =false;
  
 echo("Scanning category --> $Category...\r\n");
 echo(" building directory...");

 if ($type & $TYPE_FUZZY) {
   if (!is_dir($WORKDIR."INDEXES/FUZZY/".$Category)) {
     if (!mkdir($WORKDIR."INDEXES/FUZZY/".$Category)) die("INDEXES/FUZZY/$Category Dir Failed");
     echo("INDEXES/FUZZY/$Category Dir Created\r\n");
   }
   else {
     $filesU = scandir($WORKDIR."INDEXES/FUZZY/".$Category); 
     for ($i = 0; $i < count($filesU); $i++) {
       $filenameU =$filesU[$i];  
       if ($filenameU{0} != '.') unlink($WORKDIR."INDEXES/FUZZY/".$Category."/".$filenameU); 
     }
     echo("INDEXES/FUZZY/$Category Dir Already Exists\r\n"); 
   }
 }
 if ($type & $TYPE_DISCID) {
   if (!is_dir($WORKDIR."INDEXES/DISCID/".$Category)) {
     if (!mkdir($WORKDIR."INDEXES/DISCID/".$Category)) die("INDEXES/DISCID/$Category Dir Failed");
     echo("INDEXES/DISCID/$Category Dir Created\r\n");
   }
   else {
     $filesU = scandir($WORKDIR."INDEXES/DISCID/".$Category); 
     for ($i = 0; $i < count($filesU); $i++) {
       $filenameU =$filesU[$i];  
       if ($filenameU{0} != '.') unlink($WORKDIR."INDEXES/DISCID/".$Category."/".$filenameU); 
     }
     echo("INDEXES/DISCID/$Category Dir Already Exists\r\n"); 
   }
 }
 if ($type & $TYPE_DTITLE) {
   if (!is_dir($WORKDIR."INDEXES/DTITLE/".$Category)) {
     if (!mkdir($WORKDIR."INDEXES/DTITLE/".$Category)) die("INDEXES/DTITLE/$Category Dir Failed");
     echo("INDEXES/DTITLE/$Category Dir Created\r\n");
   }
   else {
     $filesU = scandir($WORKDIR."INDEXES/DTITLE/".$Category); 
     for ($i = 0; $i < count($filesU); $i++) {
       $filenameU =$filesU[$i];  
       if ($filenameU{0} != '.') unlink($WORKDIR."INDEXES/DTITLE/".$Category."/".$filenameU); 
     }
     echo("INDEXES/DTITLE/$Category Dir Already Exists\r\n"); 
   }
 }

 echo(" ");
 $files = scandir($CDDBDIR.$Category);
 for ($k = 0; $k < count($files); $k++) {
   $file_name = $files[$k];
   if (($file_name{0} == '.') || (!is_readable($CDDBDIR.$Category."/".$file_name))) continue;
   
   if ($prevWarning) { echo("\r\n"); $prevWarning =false; }
   echo("$file_name, ");
   if ($DO_LOG) put_log($LOG_FILE, " $file_name\r\n", true);

   $file = fopen($CDDBDIR.$Category."/".$file_name, "r");

   $SearchDiscID = true;
   $DiscID_fpos =0;
   while (!feof($file)) {
     $pline =fgets($file);
     if ($pline{0}!='#') continue; //I'm here only if i search for #FILENAME or # TRACK FRAME OFFSETS

     $line = strtoupper($pline);

     if ($SearchDiscID) { 
       $npos = strpos($line, "#FILENAME="); 
       if ($npos !== false) {
         $DiscID = strtolower(trim(substr($line, $npos+10)));
         $DiscID_fpos = ftell($file)-strlen($pline);
         if ($type & $TYPE_DISCID) {
           $file_index = fopen($WORKDIR."INDEXES/DISCID/".$Category."/".substr($DiscID, 0, 2), "a");
           fwrite($file_index, "$DiscID $DiscID_fpos\n");
           fclose($file_index); 
         }

         if ($type == $TYPE_DISCID) $SearchDiscID =true; 
         else $SearchDiscID = false;
       }
     }
     else {
       if ($type & $TYPE_FUZZY) {
         $npos = strpos($line, "TRACK FRAME OFFSETS:"); 
         if ($npos !== false) {
           $TrackOffsets = array();
           $Disclen =0;
           GetTracksLengths($file, $TrackOffsets, $Disclen);
           //Warnings
           if (($Disclen<5) || ($Disclen>5400)) { 
             echo("\r\nWARNING ($Category/$DiscID): Disclen = $Disclen"); $prevWarning =true;
             if ($DO_LOG) put_log($LOG_FILE, "\r\nWARNING ($Category/$DiscID): Disclen = $Disclen\r\n", true);
           }
           if ((count($TrackOffsets)==0) || (count($TrackOffsets)>99)) {
             if (!$prevWarning) echo("\r\nWARNING ($Category/$DiscID):");
             echo(" Tracks = ".count($TrackOffsets)); $prevWarning =true; 
             if ($DO_LOG) put_log($LOG_FILE, "\r\nWARNING ($Category/$DiscID): Tracks = ".count($TrackOffsets)."\r\n", true);
           }

             $NumTracks =count($TrackOffsets);
             $file_index = fopen($WORKDIR."INDEXES/FUZZY/".$Category."/".$NumTracks, "a");
             $OutStr = "$Disclen $DiscID $DiscID_fpos/".implode(" ", $TrackOffsets);
             fwrite($file_index, "$OutStr\n");
             fclose($file_index); 
             $SearchDiscID =true;
         }//"TRACK FRAME OFFSETS:"
       } //Type and Fuzzy

     }//else SearchDiscId
   }//while feof($file)
   fclose($file);
 }//for files

 sort_index($type, $Category);

 echo("\r\nScanning category --> $Category DONE\r\n\r\n");
}

function build_indexes($type, $Categories)
{
 $time_startx =microtime_float();
 
 global $CurCategory, $WORKDIR, $CDDBDIR, $TYPE_FUZZY, $TYPE_DISCID, $TYPE_DTITLE, $DO_LOG, $LOG_FILE;

 $ToBuild ="";
 if ($type & $TYPE_FUZZY) $ToBuild .=" Fuzzy";
 if ($type & $TYPE_DISCID) $ToBuild .=" DiscID";
 if ($type & $TYPE_DTITLE) $ToBuild .=" DTitle";

 echo("Building $ToBuild Indexes Directory...");
 if ($DO_LOG) put_log($LOG_FILE, "----------Building $ToBuild Indexes Directory ".date("D M d H:i:s Y")."----------\r\n", true);

 if ($type & $TYPE_FUZZY) {
  if (!is_dir($WORKDIR."INDEXES/FUZZY/")) {
    if (!mkdir($WORKDIR."INDEXES/FUZZY/")) die("INDEXES/FUZZY/ Dir Failed");
    echo("INDEXES/FUZZY/ Dir Created\r\n");
  }
  else { echo("INDEXES/FUZZY/ Dir Already Exists\r\n"); }
 }
 if ($type & $TYPE_DISCID) {
  if (!is_dir($WORKDIR."INDEXES/DISCID/")) {
    if (!mkdir($WORKDIR."INDEXES/DISCID/")) die("INDEXES/DISCID/ Dir Failed");
    echo("INDEXES/DISCID/ Dir Created\r\n");
  }
  else { echo("INDEXES/DISCID/ Dir Already Exists\r\n"); }
 }
 if ($type & $TYPE_DTITLE) {
  if (!is_dir($WORKDIR."INDEXES/DTITLE/")) {
    if (!mkdir($WORKDIR."INDEXES/DTITLE/")) die("INDEXES/DTITLE/ Dir Failed");
    echo("INDEXES/DTITLE/ Dir Created\r\n");
  }
  else { echo("INDEXES/DTITLE/ Dir Already Exists\r\n"); }
 }
 
 for ($i = 0; $i < count($Categories); $i++) { 
   $CurCategory = strtolower($Categories[$i]);
   if (is_dir($CDDBDIR.$CurCategory) && ($CurCategory{0} != '.')) {
     build_index($type, $CurCategory);
   }//is valid category
 }//for categories 

 $timer = (microtime_float()-$time_startx);  
 echo "Building $ToBuild Indexes Directory... Done in $timer seconds\r\n";
 if ($DO_LOG) put_log($LOG_FILE, "----------Building $ToBuild Indexes Directory Done in $timer seconds\r\n", true);
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

//Main...
GetCommandLine();
if (CheckHello()) {
 set_time_limit($SCRIPT_TIMEOUT);
 if (!isset($CMDLINE['cmd'])) die("201 $SERVER_HOST_NAME CDDBP Indexes server $VERSION.php ready at ".date("D M d H:i:s Y")."\r\n");
 echo("$SERVER_HOST_NAME CDDBP Indexes server $VERSION.php ready at ".date("D M d H:i:s Y")."\r\n");
 //$Cmd = SplitCmd($CMDLINE['cmd']);
 $Cmd = explode(" ", $CMDLINE['cmd']);
 if (count($Cmd)>0) {
   $type = $TYPE_NONE;
   $CmdStart =0;
   for ($CmdStart =0; $CmdStart < count($Cmd); $CmdStart++) {
     $xCmd = strtolower($Cmd[$CmdStart]);
     if ($xCmd == "fuzzy") { $type |= $TYPE_FUZZY; }
     elseif ($xCmd == "discid") { $type |= $TYPE_DISCID; }
     elseif ($xCmd == "dtitle") { $type |= $TYPE_DTITLE; }
     elseif ($xCmd == "all") { $type = $TYPE_FUZZY | $TYPE_DISCID | $TYPE_DTITLE; }
     else break;
   }

   if ($CmdStart < count($Cmd)) { $Categories = array_slice($Cmd, $CmdStart); }
   else { $Categories = scandir($CDDBDIR); }

   build_indexes($type, $Categories);
 }
}
else die("431 Handshake not successful, closing connection\r\n");
?>
