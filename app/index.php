<html>
    <head>
        <title>Freez It !</title>
        <link href="css/page.css" type="text/css" rel="stylesheet" />
        <script src="scripts/jquery-1.11.2.js"></script>
        <script src="scripts/layoutActions.js"></script>
        <script type="text/javascript" src="scripts/play.js">
          </script>
    </head>    
    <body>
        <!--<iframe id="frameyt" src="" height="200px" width="600px">
        </iframe>-->
        <div id="search-container">Player here</div>
        <div id="search-form-container">
             <form method="GET" action="index.php">
                 <fieldset>
                     
                <table>
                         
                    <tr><td><label>Artiste(s)</label></td>
                        <td><input type="text" name="artist" value="<?php echo filter_input(INPUT_GET, "artist") ?>"/>
                        </td>
                    </tr>
                    <tr>
                 <td><label>Album</label></td>
                 <td><input type="text" name="album" value="<?php echo filter_input(INPUT_GET, "album") ?>"/>
                 </td>
                 </tr>
                 <tr>
                     <td><label>Envoyer:</label></td><td><input type="submit" name="submit-button" value="Clic moi"/></td>
                 </tr>
                 </tr>
                </table>
                 
                 </fieldset>
            </form>
        </div>
            <?php
            include("php-gracenote/Gracenote.class.php");
            require_once("private.php");
            $api = new Gracenote\WebAPI\GracenoteWebAPI($clientID, $clientTag);

            $userID = $api->register();

            $artiste = filter_input(INPUT_GET, 'artist');
            $album = filter_input(INPUT_GET, 'album');

            if ($artiste == "" && $album == "") {
                exit(-1);
            }
            if ($album == "") {
                $results = $api->searchArtist($artiste);
            } else {
                $results = $api->searchAlbum($artiste, $album);
            }

            if (isset($results[0])) {
            ?>
                <a id='authorBioNOLinkShow' onclick='montrerBioArtist("authorBioNO")'>Monter Bio artiste</a>
                <a id='authorBioNOLinkHide' onclick='cacherBioArtist("authorBioNO");'>Cacher Bio artiste</a>
                <div id='authorBioNO'/>
                <img src='<?= $results[0]["artist_image_url"] ?>'>
                    <?= file_get_contents($results[0]["artist_bio_url"])  ?>
                </div>
                <?php
            
            }


            $i = 0;
            $j = 0;
            while (isset($results[$i])) :
                global $j;
                global $i;
                $result0 = $api->fetchAlbum($results[$i]["album_gnid"]);
                ?>
                <p class="album_title"><a href="#album<?= $i ?>" onclick='montrerAlbum("album<?= $i ?>");'>
                <?= $result0[0]["album_artist_name"] ?>, 
                <?= $result0[0]["album_title"] ?>-- date : <?= $results[$i]["album_year"] ?>
                </a></p>
                <div class='album_view' id="album<?= $i ?>">
                <p class="album_title"><a href="#album<?= $i ?>" onclick='montrerAlbum("album<?= $i ?>");'>
                        <i><?= $result0[0]["album_artist_name"] ?></i>
                <strong><?= $result0[0]["album_title"] ?></strong><?= $results[$i]["album_year"] ?>
                </a></p>
                <img src='<?= $result0[0]["album_art_url"] ?>'>
                <p class='genre_title'>Pistes</p><ul>
                    <?php
                foreach ($result0[0]['tracks'] as $track) {
                    // again echo anything here you would like.
                    ?>
                    <li><?php echo $track["track_title"] ?>
                        <input type='text' class='musicSpan' id='musicSpan<?php echo $j; ?>' 
                               value="<?php echo $result0[0]["album_artist_name"]; ?> , <?php echo $result0[0]["album_title"]; ?> , <?php echo $track["track_title"]; ?>" />
                        <input type='button' value='play song' onclick="playsong('<?php echo rawurlencode($result0[0]["album_artist_name"]." ". $result0[0]["album_title"] ." ".$track["track_title"]); ?>');" />
                    </li>
        <?php
        $j++;
    }
    echo "</ul>";
    echo "<p class='genre_title'>Genres</p><ul>";
    foreach ($result0[0]['genre'] as $genre) {
        // again echo anything here you would like.
        echo "<li>" . $genre['text'] . "</li>";
    }
    echo "</ul>";
    echo "<div class='album_review'>".file_get_contents($results[$i]["review_url"])."</div>";
    echo "</div>";
    $i++;
endwhile;
?>
            <div id="#player"></div>
          <script type="text/javascript">
              mettreEnPageInitiale();
          </script>
    </body>


</html>
