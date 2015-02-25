#PHP mail client

This mail client uses several libraries from the RoundCube project (http://roundcube.net) to implement a REST service for reading and sending emails. The file *mail.php* and the *lib* folder are designed to run on a remote server. The *email.html* file is for the local device and uses javascript to interact with the server pages. It's designed to be run as an AEK page in a campusM app.

The service includes support for listing, reading emails (including attachments) and sending plain text email.

##Configuration
The configuration options are defined at the beginning of the *lib/email.php* file.

##Text only
As the service is intended primarily for mobile devices, only text versions of emails are supported. The service uses the RoundCube library to extract text from HTML mails. There is also a routine to scan email text for URLs and email addresses and enable them as hyperlinks and mailto references.

##Attachments
The service includes a facility for downloading attachments in emails which are handled according to the defaults on the device. iOS will attempt to open the file using a suitable viewer so not all file types are supported. Android devices save it to the downloads folder.

##Sending
The default option for sending mail is to use the PHP mail function. If the Zend framework is installed on your server, you can change the config in *lib/email.php* to use the Zend mail class instead. This uses SMTP to despatch the message and has the advantage that email rejections go back to the user. Mail sent directly by PHP specifies the web server as the sender on the message envelope and therefore rejections go to the server instead.

##Screenshots
The default inbox view shows an Unseen mail counter and the total number of emails:

![Email inbox](https://raw.githubusercontent.com/davidguest/mail2/master/screenshots/inbox.png)

Reading a basic message in text...

![Example message](https://raw.githubusercontent.com/davidguest/mail2/master/screenshots/message1.png)

Any UTF-8 character sets should display fine....

![Example message with chinese characters](https://raw.githubusercontent.com/davidguest/mail2/master/screenshots/message2.png)

Attachments render in the webview on iOS (if supported) or go into the Downloads folder on Android...

![Example message with attachment](https://raw.githubusercontent.com/davidguest/mail2/master/screenshots/message3.png)

URLs and email addresses in messages are detected and turned into http or mailto links...

![Example message with links](https://raw.githubusercontent.com/davidguest/mail2/master/screenshots/message4.png)

Users can reply directly to messages or send text messages directly from the app. Messages can be sent with Zend (via SMTP) or, if the Zend framework is not installed,  despatched with PHP mail direct from the mail server.

![Sending a message](https://raw.githubusercontent.com/davidguest/mail2/master/screenshots/send.png)

