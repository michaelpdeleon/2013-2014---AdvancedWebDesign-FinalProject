<!doctype html>
<html lang="en">
    
<!-- ITI 332 - Eyes and Ears Everywhere - Group Project 
Members:
Bryan Jason Garsia
Carson Severyn
Frank Ng
Michael De Leon
-->
    <head>
        
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        
        <title>Eyes and Ears Everywhere</title>
        
        <script src="js/jquery-1.10.2.min.js"></script>
        <script src="https://maps.googleapis.com/maps/api/js?v=3.exp&sensor=false&libraries=places"></script>
                <script type="text/javascript" src="//www.google.com/jsapi"></script>
        <!-- Bootstrap core CSS -->
        <link href="bootstrap/css/bootstrap.css" rel="stylesheet">
        
        <!-- Custom styles for this template -->
        <link href="css/main.css" rel="stylesheet">
        
    </head>
    
    <body>
        <div id=main-container>
        
        
        <div id="header">
            
            <h1>Eyes and Ears Everywhere</h1>
        
            <!-- Main form -->
            <form class="form-horizontal" role="form" id="mainForm">
                
                <div class="form-group">
                    <div class="col-lg-4">
                        <input type="text" class="form-control" id="SearchTerm" placeholder="Search for: (topic)">
                    </div>
                    <div class="col-lg-2">
                        <select class="form-control" id="SearchType" size="1">
                            <option selected value="">Order by:</option>
                            <option value="MostRecent">Most Recent</option>
                            <option value="MostPopular">Most Popular</option>
                        </select>
                    </div>
                    <div class="col-lg-4">
                        <button type="submit" class="btn btn-default" id="searchButton">Search</button>
                    </div>
                </div>
            </form><!-- end of main form -->
            
        </div>
        
        <div id="map-container">
            
            <div class="col-lg-12">
                <h2>Google Map</h2>
                <div id="googleMapCanvas"></div>
                <p><img src="images/powered-by-google-on-white.png"></p>
            </div>
            
        </div>
        
        <div id="tweet-container">
            <div class="col-lg-6">
                <h2>Tweet results:</h2>
                <!-- errors if any -->
                <span id="errors"></span>
                <ul id="twitterResults"></ul>
            </div>
        </div>
            
        <div id="insta-container">    
            <div class="col-lg-8">
                <h2>Instagram results:</h2>
                <div id = "overflow">
                <ul id="instagramResults"></ul>
            </div>
        </div>    
        </div>
        </div>
        
        <div class="container">
        
            <div class="col-lg-12">
                <div class="modal fade" id="myModalWindow" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
                  <div class="modal-dialog">
                    <div class="modal-content">
                      
                      <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
              			<h4 class="modal-title"></h4>
            		  </div>
    				  
                      <div>
                        <!-- errors if any -->
                        <span id="errorsModal"></span>
                        <ol class="modal-body"></ol>
                      </div>
                      
                      <div class="modal-footer">
              			<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
              		  </div>
    				
                    </div><!-- /.modal-content -->
    			  </div><!-- /.modal-dialog -->
    			</div><!-- /.modal -->
    		</div><!-- col-lg-12 -->
        
        </div>
        
        <!-- jQuery and Ajax -->
        <script src="js/e3.js"></script>
        <!-- Bootstrap core Javascript -->
        <!-- Placed at the end of the document so the pages load faster -->
        <script src="bootstrap/js/bootstrap.min.js"></script>
    
        
        <div id="template" style="display: none">
            <li class="tweetmap"><span class="location"></span> </br><span class="author"></span> --- <span class="text"></span></li>
        </div>
        <div id="instagramTemplate" style="display: none">
            <li class="instamap"><div id="instaphoto"><span class="photo"></div></span><br/><span class="place"></span><br/><span class="user"></span> --- <span class="caption"></span></li>
        </div>
    </body>
</html>