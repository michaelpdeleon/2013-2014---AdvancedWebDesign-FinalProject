$(document).ready(function() {

    $('.modal-body').empty();
    $('#errors').empty();
    $('#errorsModal').empty();

    //hide search        
    $("#ChooseSearch").change(function() {
        var selectedSearch = $("#ChooseSearch option:selected").text();


        if (selectedSearch == 'Search by:') {
            $("#select_topic").hide();
            $("#select_location").hide();
            $("#SearchType").hide();
            $("#SearchResultsRadius").hide();
        }
        else if (selectedSearch == 'Topic') {
            $("#select_topic").show();
            $("#SearchType").show();
            $("#select_location").hide();
            $("#SearchResultsRadius").hide();
        }
        else if (selectedSearch == '+Location') {
            $("#select_topic").hide();
            $("#SearchType").hide();
            $("#select_location").show();
            $("#SearchResultsRadius").show();
        }
        else if (selectedSearch == 'Both') {
            $("#select_topic").show();
            $("#select_location").show();
            $("#SearchResultsRadius").show();
            $("#SearchType").show();
        }
    });

    //google map    
    var markersArray = [];
    var infowindow = new google.maps.InfoWindow();
    var geocoder = new google.maps.Geocoder();
    var mapOptions = {
        zoom: 2,
        center: new google.maps.LatLng(0, 0),
        mapTypeId: google.maps.MapTypeId.ROADMAP
    };
    var map = new google.maps.Map($("#googleMapCanvas").get(0), mapOptions);

    //click button
    $("#searchButton").click(function(e, center) {
        e.preventDefault();

        //clear modal
        $('.modal-body').empty();
        $('#errors').empty();
        $('#errorsModal').empty();

        map.setZoom(2);
        map.setCenter(google.maps.LatLng(0, 0));

        if ($("#SearchTerm").val().length === 0) {
            alert('Topic is required');
        }

        else if ($("#SearchTerm").val() !== 0) {
            var typepattern = /^[A-Za-z0-9#]+$/;

            if (!typepattern.test($('#SearchTerm').val())) {
                alert("Only use letters, numbers, and hashtags. No spaces");
            }
        }

        //clear tweets & instagram
        $("#twitterResults").empty();
        $("#instagramResults").empty();

        //clear map 
        for (var i = 0; i < markersArray.length; i++) {
            markersArray[i].setMap(null);
        }
        markersArray = [];

        var selectedType = $("#SearchType option:selected").text();
        if (selectedType == 'Most Recent') {
            var dataobj = {
                q: $('#SearchTerm').val(),
                count: 200,
                lang: 'en',
                result_type: "recent"
            };
        }

        else if (selectedType == 'Most Popular') {
            dataobj = {
                q: $('#SearchTerm').val(),
                count: 100,
                lang: 'en',
                result_type: "popular"
            };
        }


        else if (selectedType == 'Order by:') {
            alert("Must Choose Order of Search");
        }



        // get tweets
        $.ajax({
            data: dataobj,
            url: '/twitter.php/search/tweets.json',
            type: 'GET',
            dataType: 'json',
            success: function(serverResponse) {
                if (serverResponse.statuses.length === 0) {
                    alert("No Results for " + $('#SearchTerm').val());
                }

                var tweetResultsArray = [];
                var count = 0;
                for (var i = 0; i < serverResponse.statuses.length; i++) {
                    var tweet = serverResponse.statuses[i];
                    var item = $("#template>.tweetmap").clone();



                    if (selectedType == 'Most Recent') {
                        if (tweet.coordinates !== null && count < 5) {
                            item.appendTo("#twitterResults");
                            item.find(".author").html('<a href="javascript:" data-toggle="modal" class="twitterScreenNameResults">' + tweet.user.screen_name + '</a>');
                            item.find(".text").text(tweet.text);

                            //save matching results to an array
                            tweetResultsArray.push({
                                user_idResults: tweet.user.id,
                                screen_nameResults: tweet.user.screen_name,
                                user_tweetResults: tweet.text
                            });

                            // get location info from google
                            doMap(item, tweet);
                            count++;
                        } //if most recent
                    }

                    else if (selectedType == 'Most Popular') {
                        if (count < 5) {
                            item.appendTo("#twitterResults");
                            item.find(".author").html('<a href="javascript:" data-toggle="modal" class="twitterScreenNameResults">' + tweet.user.screen_name + '</a>');
                            item.find(".text").text(tweet.text);

                            //save matching results to an array
                            tweetResultsArray.push({
                                user_idResults: tweet.user.id,
                                screen_nameResults: tweet.user.screen_name,
                                user_tweetResults: tweet.text
                            });

                            // get location info from google
                            doMap(item, tweet);
                            count++;
                        } //if most popular
                    }



                } // for

                if (count === 0) {
                    $("#twitterResults").text("No recent tweets with known location");
                }
                else {
                    $(".twitterScreenNameResults").click(function(e) {
                        e.preventDefault();


                        for (var i = 0; i < tweetResultsArray.length; i++) {
                            if (tweetResultsArray[i].screen_nameResults === $(this).text()) {
                                $('.modal-body').empty();
                                $('#errors').empty();
                                $('#errorsModal').empty();

                                $('#myModalWindow').modal('show');
                                $('.modal-title').text('Recent tweets by ' + tweetResultsArray[i].screen_nameResults);

                                var dataobj = {
                                    screen_name: tweetResultsArray[i].screen_nameResults,
                                    user_id: tweetResultsArray[i].user_idResults,
                                    count: 30,
                                    include_rts: false
                                };

                                $.ajax({
                                    data: dataobj,
                                    url: '/twitter.php/statuses/user_timeline.json',
                                    type: 'GET',
                                    dataType: 'json',
                                    success: function(serverResponse) {
                                        var maxTweets = 10;
                                        if (serverResponse.length > 0) {
                                            for (var i = 0; i < maxTweets; i++) {
                                                var userTweet = serverResponse[i];
                                                $('.modal-body').append('<li>' + userTweet.text + '</li>');
                                            }
                                        }
                                        else {
                                            $('#errorsModal').text("Error:  No tweets found for this user.");
                                        }
                                    },
                                    error: function() {
                                        $('#errorsModal').text("Error:  A server error occurred.");
                                    }
                                }); // ajax
                            } // if
                        } // for
                    }); // twitterScreenNameResults
                } // if

            }, // success
            error: function() {
                $("#twitterResults").text("Error Loading Tweets");
            }

        });



        //make map             
        function doMap(item, tweet) {

            if (!tweet.coordinates) {
                item.find(".location").text("(no location)");
                return;
            }
            // get detailed location info from api
            $.ajax({
                url: '/googlemaps.php/json',
                data: {
                    latlng: tweet.coordinates.coordinates[1] + "," + tweet.coordinates.coordinates[0],
                    sensor: false
                },
                dataType: 'json',
                type: 'GET',
                success: function(mapResponse) {
                    if (mapResponse.results.length > 0) {
                        //item.find(".location").text(mapResponse.results[0].formatted_address);
                        item.find(".location").html(mapResponse.results[0].formatted_address + ' <a href="javascript:" class="markerResultsList"><img src="images/googleMarker10pxW.png"><span class="tweetLat" style="display:none;">' + tweet.coordinates.coordinates[1] + '</span><span class="tweetLong" style="display:none;>' + tweet.coordinates.coordinates[0] + '</span></a>');


                        $(".markerResultsList").click(function() {
                            e.preventDefault();


                            var tweetlatlongGoogle = new google.maps.LatLng(tweet.coordinates.coordinates[1], tweet.coordinates.coordinates[0]);
                            map.panTo(tweetlatlongGoogle);
                            map.setZoom(10);
                            infowindow.open(map, tweetlatlongGoogle);

                        });

                    }
                    else {
                        item.find(".location").text("(unknown location)");
                    }
                },
                error: function() {
                    item.find(".location").text("(error loading location)");
                }
            });
            // add a map marker
            var marker = new google.maps.Marker({
                map: map,
                position: new google.maps.LatLng(tweet.coordinates.coordinates[1], tweet.coordinates.coordinates[0]),
                visible: true
            });
            markersArray.push(marker);
            markerclick(marker, tweet);

        }

        function markerclick(marker, tweet) {
            google.maps.event.addListener(marker, 'click', function() {

                infowindow.setContent('<a href="javascript:" data-toggle="modal" class="twitterScreenNameMarker"><span class="author">' + tweet.user.screen_name + '</span></a> --- <span class="text">' + tweet.text + '</span>');
                infowindow.open(map, this);

                map.panTo(this.getPosition());
                map.setZoom(10);

                //open modal from a Google marker
                $(".twitterScreenNameMarker").click(function(e) {
                    e.preventDefault();

                    $('.modal-body').empty();
                    $('#errors').empty();
                    $('#errorsModal').empty();

                    $('#myModalWindow').modal('show');
                    $('.modal-title').text('Recent tweets by ' + tweet.user.screen_name);

                    var dataobj = {
                        screen_name: tweet.user.screen_name,
                        user_id: tweet.user.user_id,
                        count: 30,
                        include_rts: false
                    };

                    $.ajax({
                        data: dataobj,
                        url: '/twitter.php/statuses/user_timeline.json',
                        type: 'GET',
                        dataType: 'json',
                        success: function(serverResponse) {
                            var maxTweets = 10;
                            if (serverResponse.length > 0) {
                                for (var i = 0; i < maxTweets; i++) {
                                    var userTweet = serverResponse[i];
                                    $('.modal-body').append('<li>' + userTweet.text + '</li>');
                                }
                            }
                            else {
                                $('#errorsModal').text("Error:  No tweets found for this user.");
                            }
                        },
                        error: function() {
                            $('#errorsModal').text("Error:  A server error occurred.");
                        }
                    }); // ajax
                }); //twitterScreenNameMarker

            }); //google.maps.event.addListener
        } // markerclick  


        //get Instagram photos
        if (selectedType !== 'Order by:') {

            var clientId = "0f52943621db4618ac85025ffb904706";

            var photoobj = {
                client_id: clientId
            };

            var url = '';
            if (selectedType == 'Most Recent') {
            url = '/instagram.php/tags/' + $("#SearchTerm").val() + '/media/recent?access_token=44931301.1fb234f.2153b9f865c543249f487e99b63c7db1';
            }
            
                    //?access_token=44931301.1fb234f.2153b9f865c543249f487e99b63c7db1 
            if (selectedType == 'Most Popular') {
            url = '/instagram.php/tags/search.json';
            }

            $.ajax({
                data: photoobj,
                
                
                url: url,
                
                type: 'GET',
                dataType: 'json',
                success: function(serverResponse) {
                    if (serverResponse.data.length === 0) {
                        alert("No Results for " + $('#SearchTerm').val());
                    }

                    var instagramResultsArray = [];
                    var photoArray = [];
                    var count = 0;


                    for (var i = 0; i < serverResponse.data.length; i++) {
                        var instagram = serverResponse.data[i];
                        var item = $("#instagramTemplate>.instamap").clone();

                        if (instagram.location !== null && count < 10) {
                            item.appendTo("#instagramResults");
                            item.find(".photo").html('<a href="javascript:" data-toggle="modal" class="instagramThumbToFull"><img src="' + instagram.images.thumbnail.url + '" id="' + i + '"></a>');
                            item.find(".caption").text(instagram.caption.text);
                            item.find(".user").html('<a href="javascript:" data-toggle="modal" class="instagramScreenNameResults">' + instagram.user.username + '</a>');

                            instagramResultsArray.push({
                                user_idResults: instagram.user.id,
                                screen_nameResults: instagram.user.username,
                            });

                            photoArray.push({
                                thumb: instagram.images.thumbnail.url,
                                full: instagram.images.standard_resolution.url
                            });

                            doMap2(item, instagram);
                            count++;
                        }
                    }
                    if (count === 0) {
                        $("#instagramResults").text("No recent photos with known location");
                    }
                    else {
                        $(".instagramScreenNameResults").click(function(e) {
                            e.preventDefault();


                            for (var i = 0; i < instagramResultsArray.length; i++) {
                                if (instagramResultsArray[i].screen_nameResults === $(this).text()) {
                                    $('.modal-body').empty();
                                    $('#errors').empty();
                                    $('#errorsModal').empty();

                                    $('#myModalWindow').modal('show');
                                    $('.modal-title').text('Recent photos by ' + instagramResultsArray[i].screen_nameResults);

                                    var clientId = "0f52943621db4618ac85025ffb904706";
                                    var dataobj = {
                                        client_id: clientId
                                    };
var modalphotoArray = [];
                                    $.ajax({
                                        
                                        data: dataobj,
                                        url: '/instagram.php/users/' + instagramResultsArray[i].user_idResults + '/media/recent.json',
                                        type: 'GET',
                                        dataType: 'json',
                                        success: function(serverResponse) {

                                            var maxGrams = 10;
                                            
                                            for (var i = 0; i < maxGrams; i++) {
                                                var userGrams = serverResponse.data[i];
                                                $('.modal-body').append('<li class="modalphoto"><img src="' + userGrams.images.thumbnail.url + '"></li>');
                                                $('.modal-body').append('<li class="modalcaption">' + userGrams.caption.text + '</li>');
                                                modalphotoArray.push({
                                                    thumb: userGrams.images.thumbnail.url,
                                                    full: userGrams.images.standard_resolution.url
                                                });
                                            }
                        $(".modalphoto").click(function(e) {
                            e.preventDefault();

                            var index = $(".modalphoto").index(this);

                                    $('.modal-body').empty();
                                    $('#errors').empty();
                                    $('#errorsModal').empty();

                                    $('#myModalWindow').modal('show');

                                    $('.modal-body').append('<img src="'+modalphotoArray[index].full+'">');
                                
                        });
                                        },
                                        error: function() {
                                            $('#errorsModal').text("Error:  A server error occurred.");
                                        }
                                        

                                        
                                    }); // ajax
                                } // if
                            } // for
                        }); // instagramScreenNameResults
                        $(".instagramThumbToFull").click(function(e) {
                            e.preventDefault();

                            var index = $(".instagramThumbToFull").index(this);

                                    $('.modal-body').empty();
                                    $('#errors').empty();
                                    $('#errorsModal').empty();

                                    $('#myModalWindow').modal('show');

                                    $('.modal-body').append('<img src="'+photoArray[index].full+'">');
                                
                        });

                    } // if



                },

                error: function() {
                    $("#instagramResults").text("Error Loading Instagram Photos");
                }
            }); //Instagram



            function doMap2(item, instagram) {

                // get detailed location info from api
                $.ajax({
                    url: '/googlemaps.php/json',
                    data: {
                        latlng: instagram.location.latitude + "," + instagram.location.longitude,
                        sensor: false
                    },
                    dataType: 'json',
                    type: 'GET',
                    success: function(mapResponse) {
                        if (mapResponse.results.length > 0) {
                            //item.find(".location").text(mapResponse.results[0].formatted_address);
                            item.find(".place").html(mapResponse.results[0].formatted_address + ' <a href="javascript:" class="markerResultsList"><img src="images/googleMarker10pxW.png"><span class="instaLat" style="display:none;">' + instagram.location.latitude + '</span><span class="instaLong" style="display:none;>' + instagram.location.longitude + '</span></a>');



                            //google logo clicker
                            $(".markerResultsList").click(function() {
                                e.preventDefault();



                                var instalatlongGoogle = new google.maps.LatLng(instagram.location.latitude, instagram.location.longitude);
                                map.panTo(instalatlongGoogle);
                                map.setZoom(10);
                                infowindow.open(map, instalatlongGoogle);

                            });

                        }
                        else {
                            item.find(".place").text("(unknown location)");
                        }
                    },
                    error: function() {
                        item.find(".place").text("(error loading location)");
                    }
                });
                // add a map marker
                var marker = new google.maps.Marker({
                    map: map,
                    position: new google.maps.LatLng(instagram.location.latitude, instagram.location.longitude),
                    visible: true
                });
                markersArray.push(marker);
                markerclicker(marker, instagram);

            }

            function markerclicker(marker, instagram) {
                google.maps.event.addListener(marker, 'click', function() {

                    infowindow.setContent('<span class="photo"><img src="' + instagram.images.thumbnail.url + '"></span><br/><a href="javascript:" data-toggle="modal" class="instagramScreenNameMarker"><span class="user">' + instagram.user.username + '</span></a>');
                    infowindow.open(map, this);

                    map.panTo(this.getPosition());
                    map.setZoom(10);

                    //open modal from a Google marker
                    $(".instagramScreenNameMarker").click(function(e) {
                        e.preventDefault();

                        $('.modal-body').empty();
                        $('#errors').empty();
                        $('#errorsModal').empty();

                        $('#myModalWindow').modal('show');
                        $('.modal-title').text('Recent photos froms ' + instagram.user.username);

                        var clientId = "0f52943621db4618ac85025ffb904706";
                        var dataobj = {
                            client_id: clientId
                        };
                        var modalphotoArray = [];
                        $.ajax({
                            data: dataobj,
                            url: '/instagram.php/users/' + instagram.user.id + '/media/recent.json',
                            type: 'GET',
                            dataType: 'json',
                            success: function(serverResponse) {

                                var maxGrams = 10;

                                for (var i = 0; i < maxGrams; i++) {
                                    var userGrams = serverResponse.data[i];
                                    $('.modal-body').append('<li class="modalphoto"><img src="' + userGrams.images.thumbnail.url + '"></li>');
                                    $('.modal-body').append('<li class="modalcaption">' + userGrams.caption.text + '</li>');
                                    modalphotoArray.push({
                                        thumb: userGrams.images.thumbnail.url,
                                        full: userGrams.images.standard_resolution.url
                                    });
                                }
                        
                        $(".modalphoto").click(function(e) {
                            e.preventDefault();

                            var index = $(".modalphoto").index(this);

                                    $('.modal-body').empty();
                                    $('#errors').empty();
                                    $('#errorsModal').empty();

                                    $('#myModalWindow').modal('show');

                                    $('.modal-body').append('<img src="'+modalphotoArray[index].full+'">');
                                
                        });//modalclickenlargephoto


                            },
                            error: function() {
                                $('#errorsModal').text("Error:  A server error occurred.");
                            }
                        }); // ajax
                    }); //instagramMarker

                }); //google.maps.event.addListener
            } // markerclick  


        } //if not order by
    }); //click search


}); //document ready
