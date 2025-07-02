<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

mb_internal_encoding("UTF-8");
mb_http_output("UTF-8");

require_once 'includes/auth.php';
require_login();

require_once 'includes/api_key.php'; // loads $GOOGLE_MAPS_API_KEY

// Fetch stores with ratings (average rating)
require_once 'includes/db.php';

// Get the stores with their average rating (if any)
$stmt = $pdo->prepare("
    SELECT s.id, s.name, s.address, s.lat, s.lng,
           IFNULL(AVG(r.rating), 1) AS rating,
           MAX(v.visit_date) AS last_visit
    FROM stores s
    LEFT JOIN ratings r ON s.id = r.store_id
    LEFT JOIN visits v ON s.id = v.store_id AND v.user_id = ?
    WHERE s.lat IS NOT NULL AND s.lng IS NOT NULL
    GROUP BY s.id
");
$stmt->execute([$_SESSION['user_id'] ?? 1]);
$stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
	<meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>HOPT - Hard Off Personal Tracker</title>
    <link rel="stylesheet" href="css/style.css?4">
    <script>
        const stores = <?php echo json_encode($stores, JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo urlencode($GOOGLE_MAPS_API_KEY); ?>&libraries=places,marker&callback=initMap" async defer></script>
</head>

<body>
    <div id="map" style="height : 100%; width : 100%; top : 0; left : 0; position : absolute; z-index : 200;"></div>
	<div id="follow-button" title="Follow Me">📍</div>

	<script>
        let map;
		let userMarker = null;
		let following = false;

		const markersByStoreId = {};
		
		// icons
		const icons_unvisited = [
			"svg/lightblue_circle_circle.svg",
			"svg/lightblue_circle_diamond.svg",
			"svg/lightblue_circle_star.svg",
		];
		const icons_recent = [
			"svg/green_circle_circle.svg",
			"svg/green_circle_diamond.svg",
			"svg/green_circle_star.svg",
		];
		const icons_last_year = [
			"svg/yellow_circle_circle.svg",
			"svg/yellow_circle_diamond.svg",
			"svg/yellow_circle_star.svg",
		];
		const icons_longer = [
			"svg/orange_circle_circle.svg",
			"svg/orange_circle_diamond.svg",
			"svg/orange_circle_star.svg",
		];
		

		function bindStarEvents(container, storeId) {
			container.querySelectorAll('span').forEach(star => {
				star.addEventListener('click', () => {
					const rating = parseInt(star.getAttribute('data-value'));
					submitRating(storeId, rating);

					// Re-render the stars and re-bind
					container.innerHTML = renderStars(rating, storeId);
					bindStarEvents(container, storeId);
				});
			});
		}
		
		// render the rating stars
		function renderStars(rating, storeId) {
			const rounded = Math.round(rating);
			let html = `<div class="rating-stars" data-store-id="${storeId}">`;

			for (let i = 1; i <= 3; i++) {
				html += `<span data-value="${i}" style="cursor:pointer">${i <= rounded ? '★' : '☆'}</span>`;
			}

			html += `</div>`;
			return html;
		}
		
		// AJAX rating updater
		function submitRating(storeId, rating) {
			fetch('rate.php', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded'
				},
				body: `store_id=${storeId}&rating=${rating}`
			})
			.then(response => {
				if (!response.ok) throw new Error('Rating failed');
				return response.text();
			})
			.then(text => {
				console.log('Rating submitted:', text);
				// Update local store object so future popups reflect the change
				const store = stores.find(s => s.id == storeId);
				if (store) {
					store.rating = rating;
					const marker = markersByStoreId[storeId];
					if (marker) {
						marker.content = getPin(store);
					}
				}
			})
			.catch(err => alert('Error submitting rating: ' + err));
		}
		
		// get an icon based on the state of the store
		function getPin(store) {
			const img = document.createElement('img');
			img.style.width = "28px";
			
			icon_set = icons_unvisited;
			
			if (store.last_visit != null) {
				const lastVisit = new Date(store.last_visit);
				const now = new Date();
				const diffMs = now - lastVisit;
				const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
				if (diffDays < 60) {
					icon_set = icons_recent;
				}
				else if (diffDays < 300) {
					icon_set = icons_last_year;
				}
				//if (diffDays < (300+365)) {
				else {
					icon_set = icons_longer;
				}
			}
			
			rating = Math.round(store.rating);
			if (rating < 1) rating = 1;
			if (rating > 3) rating = 3;
			img.src = icon_set[rating - 1];
			return img;
		}

		// check if store is near enough to check in
		function checkNearStore(storeId) {
			const store = stores.find(s => s.id == storeId);
			if (!store || !store.lat || !store.lng) {
				alert("Store location is missing.");
				return false;
			}

			if (!navigator.geolocation) {
				alert("Geolocation is not supported.");
				return false;
			}

			navigator.geolocation.getCurrentPosition(
				(position) => {
					const userLat = position.coords.latitude;
					const userLng = position.coords.longitude;

					const distance = haversineDistance(
						userLat, userLng,
						parseFloat(store.lat), parseFloat(store.lng)
					);

					console.log(`Distance to store: ${distance.toFixed(1)} m`);

					if (distance > 250) {
						return false;
					}
					return true;
				}
			);
			return false;
		}

		// update last check-in time for store
		function checkIn(storeId) {
			const now = new Date();
			//const now = new Date("2024-01-01T00:00:00");

			if (navigator.geolocation) {
				if (!checkNearStore(storeId)) {
					alert("You're too far from this store to check in. (Limit: 250m)");
					return;
				}
			}

			fetch('checkin.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: `store_id=${storeId}&timestamp=${encodeURIComponent(now.toISOString())}`
			})
			.then(res => res.json())
			.then(data => {
				if (data.success) {
					// Update local store object so future popups reflect the change
					const store = stores.find(s => s.id == storeId);
					if (store) {
						store.last_visit = now;
						const marker = markersByStoreId[storeId];
						if (marker) {
							marker.content = getPin(store);
						}
					}
				} else {
					alert("Check-in failed: " + data.error);
				}
			})
			.catch(err => alert("Network error: " + err));
		}
		
		function formatDateTimeJSTIntl(isoString) {
			if (!isoString) return '';

			const jstDate = new Date(isoString);
			return new Intl.DateTimeFormat('ja-JP', {
				timeZone: 'Asia/Tokyo',
				year: 'numeric',
				month: '2-digit',
				day: '2-digit',
				hour: '2-digit',
				minute: '2-digit',
				hour12: false
			}).format(jstDate).replace(/\//g, '-');
		}

		function haversineDistance(lat1, lng1, lat2, lng2) {
			const R = 6371000; // Earth radius in meters
			const toRad = (deg) => deg * Math.PI / 180;

			const dLat = toRad(lat2 - lat1);
			const dLng = toRad(lng2 - lng1);

			const a = Math.sin(dLat / 2) ** 2 +
					  Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
					  Math.sin(dLng / 2) ** 2;

			const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

			return R * c; // in meters
		}

		// create the map
        function initMap() {
            const defaultCenter = { lat: 35.6762, lng: 139.6503 }; // Tokyo

            map = new google.maps.Map(document.getElementById('map'), {
                center: defaultCenter,
                zoom: 5,
				mapId: 'Hard_Off_Personal_Tracker'
            });
			map.controls[google.maps.ControlPosition.TOP_LEFT].push(document.getElementById('follow-button'));

			// show user's location
			const followButton = document.getElementById('follow-button');

			followButton.addEventListener('click', () => {
				following = true;

				followButton.style.backgroundColor = '#007bff';
				followButton.style.color = 'white';

				// Immediately pan to current location (if we already have one)
				if (userMarker) {
					map.panTo(userMarker.getPosition());
				}
			});

			function stopFollowing() {
				if (following) {
					following = false;
					followButton.style.backgroundColor = 'white';
					followButton.style.color = 'black';
				}
			}
			map.addListener('dragstart', stopFollowing);
			//map.addListener('zoom_changed', stopFollowing);

			navigator.geolocation.watchPosition(
				(position) => {
					console.log("Geolocation received:", position.coords.latitude, position.coords.longitude);
					const userLatLng = {
						lat: position.coords.latitude,
						lng: position.coords.longitude
					};

					// Create or move marker
					if (!userMarker) {
						userMarker = new google.maps.Marker({
							position: userLatLng,
							map,
							title: "Your Location",
							icon: {
								url: "http://maps.google.com/mapfiles/kml/paddle/blu-blank-lv.png",
								//scaledSize: new google.maps.Size(48, 48)
							}
						});
					} else {
						userMarker.setPosition(userLatLng);
					}

					// If following is active, pan to user
					if (following) {
						map.panTo(userLatLng);
					}
				},
				(err) => console.warn("Geolocation error:", err.message),
				{
					enableHighAccuracy: true,
					maximumAge: 5000,
					timeout: 10000
				}
			);


			class Popup extends google.maps.OverlayView {
				position;
				containerDiv;
				constructor(position, store) {
					super();
					this.position = position;
					
					this.containerDiv = document.createElement('div');
					this.containerDiv.className = 'popup-bubble';

					// Add close button
					const closeButton = document.createElement('button');
					closeButton.className = 'popup-close';
					closeButton.innerHTML = '&times;';
					closeButton.onclick = () => {
						this.setMap(null);  // removes the popup from the map
						onClose?.();
					};

					const titleWrapper = document.createElement('div');
					titleWrapper.className = 'popup-title';
					titleWrapper.innerHTML = store.name;

					//var content = "<a href=https://www.google.com/maps/search/?api=1&";
					var content = "<a href=https://www.google.com/maps?q=";
					content += encodeURI(store.name);
					content += encodeURI(", ");
					content += encodeURI(store.address);
					content += ">";
					content += store.address;
					content += "</a><br>"
					if (store.last_visit != null) {
						content += "Last visit: ";
						const date = new Date(store.last_visit);
						content += formatDateTimeJSTIntl(date);
						content += "<br>";
					}
					content += "<div class='rating-checkin-div'>";
					content += `${renderStars(store.rating, store.id)}`;
					content += "<button class='check-in-button' onclick='checkIn(" + store.id + ")'>Check in</button></div>";

					const contentWrapper = document.createElement('div');
					contentWrapper.className = 'popup-content';
					contentWrapper.innerHTML = content;

					this.containerDiv.appendChild(closeButton);
					this.containerDiv.appendChild(titleWrapper);
					this.containerDiv.appendChild(contentWrapper);
					this.containerDiv.style.position = 'absolute';

					// Optionally stop clicks, etc., from bubbling up to the map.
					Popup.preventMapHitsAndGesturesFrom(this.containerDiv);
				}
				/** Called when the popup is added to the map. */
				onAdd() {
					this.getPanes().floatPane.appendChild(this.containerDiv);
				}
				/** Called when the popup is removed from the map. */
				onRemove() {
					if (this.containerDiv.parentElement) {
						this.containerDiv.parentElement.removeChild(this.containerDiv);
					}
				}
				/** Called each frame when the popup needs to draw itself. */
				draw() {
					const divPosition = this.getProjection().fromLatLngToDivPixel(this.position,);
					// Hide the popup when it is far out of view.
					const display =
						Math.abs(divPosition.x) < 4000 && Math.abs(divPosition.y) < 4000
							? "block"
							: "none";

					if (display === "block") {
						this.containerDiv.style.left = divPosition.x + "px";
						this.containerDiv.style.top = divPosition.y + "px";
					}

					if (this.containerDiv.style.display !== display) {
						this.containerDiv.style.display = display;
					}
				}
			}


			let currentPopup = null;
			
			
			// Close any open infoWindow when clicking on the map
			map.addListener('click', () => {
				if (currentPopup) {
					currentPopup.setMap(null);
					currentPopup = null;
				}
			});

			// populate stores
            stores.forEach(store => {
                if (!store.lat || !store.lng) return;

                const marker = new google.maps.marker.AdvancedMarkerElement({
                    position: { lat: parseFloat(store.lat), lng: parseFloat(store.lng) },
                    map: map,
					gmpClickable: true,
                    title: store.name,
					content: getPin(store),
                });
				markersByStoreId[store.id] = marker;

                marker.addListener('click', ({ domEvent, latLng }) => {
					if (currentPopup) {
						currentPopup.setMap(null);
						currentPopup = null;
					}
					currentPopup  = new Popup(
						marker.position,
						store,
					);
					currentPopup.setMap(map);
					
					// rating updates
					setTimeout(() => {
						const container = document.querySelector(`.rating-stars[data-store-id="${store.id}"]`);
						if (!container) return;
						
						bindStarEvents(container, store.id);
					}, 50); // wait for popup to render
                });
            });
        }
    </script>
</body>
</html>
