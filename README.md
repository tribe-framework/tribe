# Tribe

### quick install
```
sh -c "$(wget https://raw.githubusercontent.com/wil-ldf-ire/tribe/master/install/install.sh -O -)"
```

### updating tribe
```
sudo composer update
```

### for ember app installation
In local dev machine
```
ember init --skip-git;
ember install ember-auto-import;
ember install ember-cli-sass;
ember serve;
```
To upload to remove prod server, create build in local machine
```
ember build -o theme/dist;
```
To get process ID on local machine
```
sudo lsof -i tcp:port_num
```

### Tribe - a web project management system by wildfire.

A few config files to get started with Tribe:
- .env - variables like db connection, third-party APIs
- types.json - project's information architecture
- menus.json - navigation menus

Install folder contains all installation scripts. Gets deleted on installation.
