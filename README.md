#PHP mail client

This mail client uses several libraries from the RoundCube project to implement a REST service for reading and sending emails. The file *mail.php* and the *lib* folder are designed to run on a remote server. The *email.html* file is for the local device and uses javascript to interact with the server pages. It's designed to be run as an AEK page in a campusM app.

The service includes support for listing, reading and sending emails. Some of the key features:

##Text only
As the service is intended primarily for mobile devices, only text versions of emails are supported. The service uses the RoundCube library to extract text from HTML mails. There is also a routine to scan email text for URLs and email addresses and enable them as hyperlinks and mailto references.

##Attachments
The service includes a facility for downloading attachments in emails which are handled according to the defaults on the device. iOS will attempt to open the file using a suitable viewer so not all file types are supported. Android devices save it to the downloads folder.

##Sending
The default option for sending mail is to use the PHP mail function. If the Zend framework is installed on your server, you can change the config in *lib/email.php* to use the Zend mail class instead. This uses SMTP to despatch the message and has the advantage that email rejections go back to the user. Mail sent directly by PHP specifies the web server as the sender on the message envelope and therefore rejections go to the server instead.

See the screenshots for a view of the service in action on the Sussex mobile app.
