
<html>
    <head>
        <title>Freez It !</title>
       <script type="text/javascript" src="http://www.google.com/jsapi?key=VOTRE_CLE"></script>
<script type="text/javascript">google.load("jquery", "1.8");</script>
    </head>    
    <body>
        
        <form method="GET" action="index.php">
            Artiste(s) <input type="text" name="artist" 
                   value="<?php echo filter_input(INPUT_GET, "artist")?>"/><br/>
            Album <input type="text" name="album" 
                   value="<?php echo filter_input(INPUT_GET, "album")?>"/><br/>
            <input type="submit" name="submit" value="FI Dis-moi quoi??"/><br/>
        </form>
<?php
include("php-gracenote/Gracenote.class.php");

$clientID  = "6368512-177768BC560A8B60B0A8184FF6B0C7D4"; 
$clientTag = "177768BC560A8B60B0A8184FF6B0C7D4";

$api = new Gracenote\WebAPI\GracenoteWebAPI($clientID, $clientTag); 

$userID = $api->register();

$artiste = filter_input( INPUT_GET,'artist');
$album = filter_input( INPUT_GET, 'album');

if($artiste=="" && $album=="")
{
    exit(-1);
}
if($album=="")
{
    $results = $api->searchArtist($artiste);
}
 else {
    $results = $api->searchAlbum($artiste,$album);
}

if(isset($results[0]))
{
    echo "<img src='".$results[0]["artist_image_url"]  ."'><span id='authorBioNO'/>";
    echo $results[0]["artist_bio_url"];
}


$i=0;
while (isset($results[$i])) :
    ?>
<?php
$result0 = $api->fetchAlbum($results[$i]["album_gnid"]);
echo "<img src='".($result0[0]["album_art_url"])."'>";
echo "<h2><a href='album='>";
echo "@" .$result0[0]["album_artist_name"]." # ";
echo $result0[0]["album_title"] . ', date : '.$results[$i]["album_year"];
echo "</a></h2>";
// loop subarrays
echo "<ul>";
    foreach($result0[0]['tracks'] as $track){
        // again echo anything here you would like.
        echo "<li>".$track["track_title"]."</li>";
    }
echo "</ul>";
echo "<ul>";
    foreach($result0[0]['genre'] as $genre){
        // again echo anything here you would like.
        echo "<li>".$genre['text']."</li>";
    }
echo "</ul>";
            ?>
<script>
    echo $results["review_url"];
</script>
        <?php

$i++;
endwhile;

?>
        <textarea><?php print_r($results);?></textarea>

        <script>
        
</script>
    </body>

    
    </html>
