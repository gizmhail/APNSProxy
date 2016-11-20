Sends notification requests to Apple Push notification server based on stored pem files
The credential direcctory should contains for each appName which s using the proxy :
* appName_dev.pem, appName_dev.pass, appName_dev.auth
* AND/OR
* appName_prod.pem, appName_prod.pass, appName_prod.auth
 
Once all your certificates uploaded, or if you want to upload them manually, you should remove the UploadCredentials.php file.

Note: to obtain .pem file from .p12 file:
```
openssl pkcs12 -in apns-dev-cert.p12 -out apns-dev-cert.pem -nodes -clcerts
```
