# Tribe

### quick install
```
sudo wget https://raw.githubusercontent.com/wil-ldf-ire/tribe/develop/0.1.0/install/install.sh; sudo bash ./install.sh; sudo rm ./install.sh;
```

### for vue-nuxt app
```
sudo yarn dev
```
to change server port, edit in file /etc/nginx/sites-enabled/<app.domain.tld>

### for php
```
sudo php composer.phar dump-autoload
```

### A web project management system by wildfire.

At the core of a Tribe installation, there are 4 files:
- tribe.var.env - variables like db connection, third-party APIs
- tribe.conf.json - root folder structure, file management
- types.json - project's information architecture
- menus.json - navigation menus

Install folder contains all installation scripts. Gets deleted on installation.