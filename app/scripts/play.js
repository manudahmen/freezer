          // Search for a specified string.
            function playsong(searchStr) {
                $("#search-container").html("regarde y a rien pour le moment!" + 
                "<a href='https://www.youtube.com/results?search_query=" +
                searchStr+"' target='new'>link</a>");
            }
            
                $( "a" ).click(function( event ) {
                event.preventDefault();
                playsong($( this ).attr("value"));
            });
            
