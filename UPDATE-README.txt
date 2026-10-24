HOTELGO update 3: landing page in the elegant resort style (same colours)
=========================================================================
This zip is the WHOLE project (without .env and vendor). Copying it over update 1 / 2 is safe.

STEP 1  Copy everything in this folder over your hotelgo project folder ("Replace the files"). .env is not touched.
STEP 2  php -S localhost:8000 -t public   -> http://localhost:8000
STEP 3  git add . / git commit -m "Landing page v3" / git push, wait for Render to say Live.

REAL PHOTOS  (this is what makes it look like the video)
  Open  public/assets/img/photos/README.txt  . Copy your pictures there with these names:
    hero.jpg  about.jpg  quote.jpg  room1.jpg room2.jpg room3.jpg room4.jpg  cta.jpg  gallery1.jpg ... gallery6.jpg
  (.jpg .jpeg .webp .png all work). No code change: a photo replaces the drawn scene automatically.
  Use your own motel / Puerto Galera pictures, or free-licence photos (Unsplash, Pexels). Do not copy pictures from
  other hotels' websites or from the video. Keep each file under ~400 KB.

Other things you can edit
  Contact email / phone / location ... app/config/hotelgo.php (hotelgo_contact_*)
  More motels on the map ............... app/config/hotelgo.php (hotelgo_places)
  Guest voices (labelled samples) ...... $voices at the top of app/views/home/index.php
