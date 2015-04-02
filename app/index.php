<html>
    <head>
        <title>Freez It !</title>
        <link href="css/page.css" type="text/css" rel="stylesheet" />
        <script src="scripts/jquery-1.11.2.js"></script>
        <script type="text/javascript">
            // Search for a specified string.
            function playsong(searchStr) {
                var input = document.getElementById(searchStr);

                $("#player").html(input.innerHTML = "regarde y a rien pour le moment!" + 
                "<a href='https://www.youtube.com/results?search_query="
                encodeURIComponent(searchStr)+"' target='new'>link</a>");

                var q = input.value;

                var request = gapi.client.youtube.search.list({
                    q: q,
                    part: 'snippet'
                });

                request.execute(function (response) {
                    var str = JSON.stringify(response.result);
                    $('#search-container').html('<pre>' + str + '</pre>');

                });
            }
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
                echo "<img src='" . $results[0]["artist_image_url"] . "'><span id='authorBioNO'/>";
                echo file_get_contents($results[0]["artist_bio_url"]);
            }


            $i = 0;
            $j = 0;
            while (isset($results[$i])) :
                global $j;
                global $i;
                $result0 = $api->fetchAlbum($results[$i]["album_gnid"]);
                echo "<img src='" . ($result0[0]["album_art_url"]) . "'>";
                echo "<h2><a href='album='>";
                echo "@" . $result0[0]["album_artist_name"] . " # ";
                echo $result0[0]["album_title"] . ', date : ' . $results[$i]["album_year"];
                echo "</a></h2>";
// loop subarrays
                echo "<ul>";
                foreach ($result0[0]['tracks'] as $track) {
                    // again echo anything here you would like.
                    ?>
                    <li><?php echo $track["track_title"]; ?>
                        <input type='text' class='musicSpan' id='musicSpan<?php echo $j; ?>' 
                               value="<?php echo $result0[0]["album_artist_name"]; ?> , <?php echo $result0[0]["album_title"]; ?> , <?php echo $track["track_title"]; ?>" />
                        <input type='button' value='play song' onclick="playsong('musicSpan<?php echo $j; ?>');" />
                    </li>
        <?php
        $j++;
    }
    echo "</ul>";
    echo "<ul>";
    foreach ($result0[0]['genre'] as $genre) {
        // again echo anything here you would like.
        echo "<li>" . $genre['text'] . "</li>";
    }
    echo "</ul>";
    echo file_get_contents($results[$i]["review_url"]);

    $i++;
endwhile;
?>
            <div id="#player"></div>
    </body>


</html>
