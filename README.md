# Monolog-Quill

**Requires PHP 7.**

Want to use [Monolog](https://github.com/Seldaek/monolog) to write security events to
a [Chronicle](https://github.com/paragonie/chronicle)?

## Installation

```bash
composer require paragonie/monolog-quill
```

### Usage

```php
<?php

use Monolog\Logger;
use ParagonIE\MonologQuill\QuillHandler;
use ParagonIE\Quill\Quill;
use ParagonIE\ConstantTime\Base64UrlSafe;
use ParagonIE\Sapient\CryptographyKeys\{
    SigningSecretKey,
    SigningPublicKey
};

// Create a Quill for writing data to the Chronicle instance 
$quill = (new Quill())
    ->setChronicleURL('https://chronicle-public-test.paragonie.com/chronicle')
    ->setServerPublicKey(
        new SigningPublicKey(
            Base64UrlSafe::decode('3BK4hOYTWJbLV5QdqS-DFKEYOMKd-G5M9BvfbqG1ICI=')
        )
    )
    ->setClientID('**Your Client ID provided by the Chronicle here**')
    ->setClientSecretKey(
        new SigningSecretKey('/* Loaded from the filesystem or something. */')
    );

// Push the Handler to Monolog
$log = new Logger('security');
$log->pushHandler(new QuillHandler($quill, Logger::ALERT));

// Now security events will be logged in your Chronicle
$log->alert(
    'User bob logged in at ' .
    ((new DateTime())->format(\DateTime::ATOM))
);
```
