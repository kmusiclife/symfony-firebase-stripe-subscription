# Symfony and Firebase Authentication

To get started, just clone the this repository and run below commands:

    git clone https://github.com/kmusiclife/symfony-firebase-auth.git
    cd symfony-firebase-auth
    composer install
    cp .env.dist .env
    cp public/js/firebase-config.js.dist public/js/firebase-config.js

1. Edit and add Firebase SDK snippet(Web App) to public/js/firebase-config.js
2. Download your-projectname-firebase-adminsdk-xxxxx.json from Firebase Admin SDK in Firebase's Service Account and copy credentials.json to your project dir

after that, easy to start your server like below command

    symfony server:start
