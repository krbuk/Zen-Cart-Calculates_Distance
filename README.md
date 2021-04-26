# Zen-Cart-Calculates_Distance
Google Distance Matrix API for your Zen-Cart Shiping module web store
Always perform a backup of your database and source code before installing any payment extensions.

 *This module works on Zen-Cart 1.5.7 If you find some bug please inform me.
  Calculates the distance between two address
  From your shoping adress to customer delivery address
  
The distance calculation is useful when your zen-cart with the user’s location. You can easily calculate the distance between addresses using Google Maps API and PHP ver 7.2. In this tutorial, we will show you how to calculate distance between two addresses with Google Maps Geocoding API using PHP.

To use the Google Maps Geocoding API, you need to specify the API Key in your request. Before getting started, generate an API key on Google Cloud Platform Console for Geocoding API.

 * Specify your Google Maps API key
 * Change the format of the addresses.
 * Calculate the distance between addres format.
 
How to Get Google Maps Geocoding API Key
An API key is required to access Google Maps Geocoding API. You need to specify the API key in Geocoding API request. In this guide, we will show you how to get API key for accessing Google Maps Geocoding API.

You can generate the API key on Google Cloud Platform Console. Follow the below steps to get an API key.

   1- Login to your Google account and go to the Google Cloud Platform Console.
   2- Select a project from Project drop-down, or create if you don’t already have.
   3- From the left navigation menu panel, select APIs & Services » Library.
   4- On the API Library page, search and enable the Geocoding API library.
   5- From the left navigation menu panel, select APIs & Services » Credentials.
   6- On the Credentials page, click Create credentials » API key.

A dialog box will appear containing your newly created API Key. Specify this API key in key parameter on Google Maps Geocoding API request.
https://developers.google.com/maps/documentation/geocoding/get-api-key