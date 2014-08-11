<?php
	if (!empty($_SESSION[$_POST['lis_result_sourcedid']]['lis_outcome_service_url'])) {
		// Give participation mark
		$student_grade = 1;

		$grade_message = 'Thanks for responding. You have been given participation mark.';

		$outcome_url = $_SESSION[$_POST['lis_result_sourcedid']]['lis_outcome_service_url'];
		$consumer_key = $_SESSION[$_POST['lis_result_sourcedid']]['oauth_consumer_key'];
		$consumer_secret = '123456';

		$hmac_method = new OAuthSignatureMethod_HMAC_SHA1();

		$body = '<?xml version = "1.0" encoding = "UTF-8"?>
		<imsx_POXEnvelopeRequest xmlns = "http://www.imsglobal.org/services/ltiv1p1/xsd/imsoms_v1p0">
			<imsx_POXHeader>
				<imsx_POXRequestHeaderInfo>
					<imsx_version>V1.0</imsx_version>
					<imsx_messageIdentifier>' . $_SERVER['REQUEST_TIME'] . '</imsx_messageIdentifier>
				</imsx_POXRequestHeaderInfo>
			</imsx_POXHeader>
			<imsx_POXBody>
				<replaceResultRequest>
					<resultRecord>
						<sourcedGUID>
							<sourcedId>' . $_POST['lis_result_sourcedid'] . '</sourcedId>
						</sourcedGUID>
						<result>
							<resultScore>
								<language>en</language>
								<textString>' . $student_grade . '</textString>
							</resultScore>
						</result>
					</resultRecord>
				</replaceResultRequest>
			</imsx_POXBody>
		</imsx_POXEnvelopeRequest>';

		$hash = base64_encode(sha1($body, TRUE));
			$params = array('oauth_body_hash' => $hash);
		$token = '';
		$content_type = 'application/xml';

		$hmac_method = new OAuthSignatureMethod_HMAC_SHA1();
		$consumer = new OAuthConsumer($consumer_key, $consumer_secret);
		$outcome_request = OAuthRequest::from_consumer_and_token($consumer, $token, 'POST', $outcome_url, $params);
		$outcome_request->sign_request($hmac_method, $consumer, $token);

		$header = $outcome_request->to_header();
		$header = $header . "\r\nContent-type: " . $content_type . "\r\n";
		$options = array(
			'http' => array(
				'method' => 'POST',
				'content' => $body,
				'header' => $header,
			),
		);

		$ctx = stream_context_create($options);
		$fp = @fopen($outcome_url, 'rb', FALSE, $ctx);
		$response = @stream_get_contents($fp);
	}
?>

<html>
	<head>
		<link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap.min.css"/>
		<link rel="stylesheet" href="css/response-map.css"/>

		<script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
		<script src="https://maps.googleapis.com/maps/api/js?v=3.exp"></script>
		<script src="https://google-maps-utility-library-v3.googlecode.com/svn/trunk/markerclusterer/src/markerclusterer.js"></script>
		<script src="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/js/bootstrap.min.js"></script>
		<script src="//cdnjs.cloudflare.com/ajax/libs/d3/3.4.11/d3.min.js"></script>
		<script src="js/d3.layout.cloud.js"></script>

		<script type="text/javascript">
			var allStudentResponses = '<?php echo $all_student_responses ?>';
			var mapResponses = JSON.parse(allStudentResponses);
			var startLocation = new google.maps.LatLng(mapResponses[0].lat, mapResponses[0].lng);

			var markerBounds = new google.maps.LatLngBounds();
			var iterator = 0;
			var map, markers = [];
			var openedInfoWindow = null;

			function mapInitialise() {
				var mapOptions = {
					center: startLocation,
					zoomControl: true,
					streetViewControl: false,
				};

				map = new google.maps.Map(
					document.getElementById('map-canvas'),
					mapOptions
				);

				for (var key in mapResponses) {					
					// First marker contains student's own response
					if (key === 0) {
						// Convert first marker lat and long to float for use in distance calculations
						mapResponses[0].lat = parseFloat(mapResponses[0].lat);
						mapResponses[0].lng = parseFloat(mapResponses[0].lng);

						// First marker is the centre marker
						mapResponses[0].distanceToCentre = 0;

						mapResponses[0].myMarker = true;
					}
					else {
						// Randomly nudge markers slightly to avoid overlap
						var nudgeLat = Math.random() * 0.00005;
						nudgeLat *= Math.floor(Math.random()*2) == 1 ? 1 : -1;

						var nudgeLng = Math.random() * 0.00005;
						nudgeLng *= Math.floor(Math.random()*2) == 1 ? 1 : -1;

						mapResponses[key].lat = parseFloat(mapResponses[key].lat) + nudgeLat;
						mapResponses[key].lng = parseFloat(mapResponses[key].lng) + nudgeLng;

						// Calculate the distance to centre marker
						mapResponses[key].distanceToCentre = Math.sqrt(Math.pow(mapResponses[key].lat - mapResponses[0].lat, 2) + Math.pow(mapResponses[key].lng - mapResponses[0].lng, 2));
					
						mapResponses[key].myMarker = false;
					}

					var marker = new google.maps.Marker({
						position: new google.maps.LatLng(mapResponses[key].lat, mapResponses[key].lng),
						map: map,
						draggable: false
					});

					marker.distanceToCentre = mapResponses[key].distanceToCentre;
					marker.myMarker = mapResponses[key].myMarker;
					marker.fullImageUrl = mapResponses[key].image_url;
					marker.fullname = mapResponses[key].fullname;

					var contentString = '<div id="content">' +
											'<h3 id="firstHeading" class="firstHeading">' + mapResponses[key].fullname + '</h3>' +
											'<div id="bodyContent">' +
												'<p>' + mapResponses[key].response + '</p>';

					if ((mapResponses[key].thumbnail_url !== null) && (mapResponses[key].image_url !== null)) {
						contentString += 		'<a href="#myModal" data-toggle="modal"><img src="' + mapResponses[key].thumbnail_url + '" alt=""/></a>';
					}

					contentString +=		'</div>' +
										'</div>';

					marker.infoWindow = new google.maps.InfoWindow({
						content: contentString
					});

					markers.push(marker);

					google.maps.event.addListener(marker, 'click', function() {
						if (openedInfoWindow !== null) {
							openedInfoWindow.close();
							openedInfoWindow = null;
						}

						$('.response-full-image').attr('src', this.fullImageUrl);
						$('.response-fullname').text(this.fullname + '\'s Image Response');

						openedInfoWindow = this.infoWindow.open(map,this);
						openedInfoWindow = this.infoWindow;

					});
				}

				// Sort the markers based on distance to center point
				markers.sort(function(a, b) {
					return (a.distanceToCentre - b.distanceToCentre);
				});

				// Only fit the view to a maximum of 20 closest markers
				var numVisibleMarkers = markers.length >= 28 ? 28 : markers.length;

				for (var i = 0; i < numVisibleMarkers; i++) {
					markerBounds.extend(markers[i].getPosition());
				}

				map.fitBounds(markerBounds);

				var mcOptions = {
					gridSize: 50,
					maxZoom: 18
				};

				var markerCluster = new MarkerClusterer(map, markers, mcOptions);
			}

			google.maps.event.addDomListener(window, 'load', mapInitialise);

			// Plotting word cloud
			$(function() {
				var frequencyList = <?php echo $word_frequency ?>;

				var color = d3.scale.linear()
					.domain([0,1,2,3,4,5,6,10,15,20,100])
					.range(['#ddd', '#ccc', '#bbb', '#aaa', '#999', '#888', '#777', '#666', '#555', '#444', '#333', '#222']);

				d3.layout.cloud().size([750, 240])
					.words(frequencyList)
					.rotate(0)
					.fontSize(function(d) { return d.size; })
					.on('end', draw)
					.start();

				function draw(words) {
					d3.select('.response-word-cloud').append('svg')
						.attr('width', 772)
						.attr('height', 250)
						.attr('class', 'wordcloud')
						.append('g')
						// without the transform, words words would get cutoff to the left and top, they would
						// appear outside of the SVG area
						.attr('transform', 'translate(340,125)')
						.selectAll('text')
						.data(words)
						.enter().append('text')
						.style('font-size', function(d) { return d.size + 'px'; })
						.style('fill', function(d, i) { return color(i); })
						.attr('transform', function(d) {
							return 'translate(' + [d.x, d.y] + ')rotate(' + d.rotate + ')';
						})
						.text(function(d) { return d.text; });
				}
			});
		</script>
	</head>

	<div id="map-canvas"></div>

	<div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal">
						<span aria-hidden="true">&times;</span>
						<span class="sr-only">Close</span>
					</button>
					<h4 class="modal-title response-fullname"></h4>
				</div>
				<div class="modal-body">
					<img class="response-full-image" src=""></img>
				</div>
			</div>
		</div>
	</div>
	<div class="response-word-cloud"></div>
</html>