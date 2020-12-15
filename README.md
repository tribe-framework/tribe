# Tribe

### quick install
```
sudo wget https://raw.githubusercontent.com/wil-ldf-ire/tribe/develop/1.0.0/install/install.sh; sudo bash ./install.sh; sudo rm ./install.sh;
```

### for vue-nuxt app
```
sudo yarn dev
pm2 --name app.domain.tld start "sudo yarn dev --port port_num"
sudo lsof -i tcp:port_num
```
to change server port, edit in file /etc/nginx/sites-enabled/<app.domain.tld>

### for php
```
sudo php composer.phar dump-autoload
```

### A web project management system by wildfire.

At the core of a Tribe installation, there are 4 files:
- .env - variables like db connection, third-party APIs
- types.json - project's information architecture
- menus.json - navigation menus

Install folder contains all installation scripts. Gets deleted on installation.