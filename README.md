# EventAPI Application 

## Installation

1. Download [Composer](https://getcomposer.org/doc/00-intro.md)

run

```bash
composer run initialize
```


add this to your hosts file
```bash
10.7.0.7 cake.local
```

now access
```
http://cake.local/api-docs
```

OR 
you can run host the application on a dockerized environment
but first you have to install docker on your machine, you can visit [this](https://docs.docker.com/desktop/#download-and-install) for the installation.  

once done, you can now start the API using this simple commands.

```bash
composer run start-server
```

after a minute or two, run this composer script:
```bash
composer run initialize
```

note: If the migrations seed did not populate the database, you have to re-run the seed command to do so. initializing the mysql service takes time.