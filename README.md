# Hard Off Personal Tracker

Track your visits to Hard Off with this web app.

Requires an SQL database and PHP. Hard Off locations can be scraped from the Hard Off website into a CSV file (see demo file) and uploaded.

Currently implemented features:

* Shows the location of imported Hard Off stores on a Google Map.
* Desktop and mobile versions.
* Each store can be clicked/tapped on to display its name, address, and star rating.
* The address links to Google Maps (can open the app on Android).
* A "check in" button allows the user to log visits to stores.
* If your browser allows geolocation, your position is displayed on the map.
* The map can follow your position with the pin toggle at the top.
* If your location is known, you can only check in to stores that are less than 250m away.
* Store icons are coloured according to how recently they were visited.
    + Blue - not visited
    + Green - visited within the last 60 days
    + Yellow - visited wthin the last 1 year 60 days
    + Orange - visited longer than 1 year 60 days ago
* Store icons have a glyph that represents the rating.
    + Circle - 1 star
    + Diamond - 2 stars
    + Star - 3 stars

To-do:

* Mark removed stores as permanently closed when importing a CSV file.
* Photo upload, one for each store.
* Notes for each store.
* Stats screen.
