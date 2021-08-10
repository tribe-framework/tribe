# Tribe - a web project management framework by wildfire.

Tribe is a project management framework that allows us to build our clients' vision using a modular approach that is friendly to various tech stacks (php, nodejs and python) and provides a coherent language for UX and design collaborations. Currently powering 30+ projects in production.

## Minimum system requirements
- independent cloud or dedicated server
- 1gb ram
- 1 core
- ubuntu 20.04
- make your server ready for tribe using - https://github.com/wil-ldf-ire/server-wildfire

## Install instructions
```
sudo bash -c "$(wget --no-cache --no-cookie https://raw.githubusercontent.com/wil-ldf-ire/tribe/master/install/install.sh -O -)"
```
#### Export instructions
```
sudo bash -c "$(wget --no-cache --no-cookie https://raw.githubusercontent.com/wil-ldf-ire/tribe/master/install/export.sh -O -)"
```
#### Import instructions
```
sudo bash -c "$(wget --no-cache --no-cookie https://raw.githubusercontent.com/wil-ldf-ire/tribe/master/install/import.sh -O -)"
```
## Quick config

1. install folder contains all installation scripts, gets deleted on installation. make sure it is not available in your host's folder structure.

2. a few config files to get started with Tribe quickly.
- define msyql variables in .env file
- visit your BASE_URL
- visit /admin, log in using combination of email address and database password stored in .env file
- modify /theme/pages/index.php
- modify types.json to define content data types and user roles
- modify /assets/css/custom.css and /assets/js/custom.js

## Advanced usage

### Architecture overview
![Tribe architecture](https://wildfiretech.co/theme/assets/img/tribe.png "Our approach")

### Config files
1. use /.env for defining environment variables you need to use, like db connection, third-party APIs
2. use /config/types.json to update post-type and user-role details
3. have a look at /config/config.php, you might need it for advanced usage

### Terminology, ideas and definitions

#### Variables available
1. $\_ENV['BASE_URL'] - the website url
2. $\_ENV['TRIBE_ROOT'] - absolute path to your website's directory
3. $\_ENV['THEME_URL'] - web link to theme folder

#### Data-types
3. post type - a content datatype
4. post - a single content piece
5. user - a single person
6. user role - people can be assigned roles

#### Definitions
1. tribe is folder structure currently in active development, through the project we're trying to build a coherent framework for design, ux and tech people to work collaboratively
2. our admin is called junction - it's where people of your tribe meet everyday, it's a content management system
3. theme is the front-end interface that is executed using php, more on this in theme section below
4. applications are front-end interfaces executed using nodejs
5. our nginx config and folder structure support emberjs applications without any server-side configuration, more on this in applications section below
6. types.json is a config file that can be used to configure data-types, once configured those data-types are available in junction and you can start adding data and users from there
7. assets are things that developers add themselves to the app, like images and fonts
8. dependencies are resources that come from third-party libraries

#### Post types in detail
1. single.php is general post layout
2. create single-&lt;ID&gt;.php for layout for specific post
3. archive.php is general multiple-stories list layout for any post-type
4. archive-&lt;type-name&gt;.php is list layout of a particular type
5. single-&lt;type-name&gt;.php is layout for individual post of a particular post-type

#### User roles in detail
1. there are 4 broad user-roles
2. admin users have access to everything
3. crew users have access to junction at /admin, but do not have ability to make things public
4. member users and visitor users do not have access to dashboard
5. member and visitor user roles can be differentiated in the theme usage
6. user interface for member users can be modified by including php files in theme folder with these names: user-header.php, user-footer.php, user-index.php, user-dashboard.php and so on. file name references from https://github.com/wil-ldf-ire/auth repository.

### Tribe utilities
1. most important functions from core features are $dash->push_content(array) and $dash->get_content($id)
2. when using $dash->push_content, the array passed needs to have 'type' and 'content_privacy'. if 'id' is available in the array, it modifies the exiting row
3. have a look at https://github.com/wil-ldf-ire/core for more details on available classes
4. visit /admin in the browser - a backend CMS called junction, log in using email address and database password defined in /.env file, visit https://github.com/wil-ldf-ire/admin for more info about it's codebase
5. our user authentication code is available at https://github.com/wil-ldf-ire/auth
6. /logs/ folder gives access to nginx access and error logs, these files cannot be accessed by the public, need root access

### Theme
1. within /theme folder, pages/index.php is the file that loads on the root URL. pages/\_header.php, pages/\_footer.php are what they sound like. you can create more files like \_nav.php, \_blog.php etc, based on your requirements.
2. includes/\_init.php makes wildfire/core functions available to the theme.
3. functions.php offers a Functions class that can be used for theme-specific use.

### Bootstrap
1. our latest theme uses bootstrap version 5
2. bootstrap is installed using npm
3. css and js files are included in /theme/pages/\_header.php and /theme/pages/\_footer.php

### SASS/SCSS Compiler
1. scss compiler is available at /theme/assets/scss/init.php
2. bootstrap scss is available at /node_modules/bootstrap/scss/
3. read more about bootstrap scss here - https://getbootstrap.com/docs/5.0/getting-started/introduction/

### Database structure
1. there are 2 tables - data and trac. both tables have same table structure
2. tribe uses json heavily, the need for creating new tables is eleminated by use of json
3. the column 'content' uses json, example usage:
```$sql->executeSQL("SELECT \`id\` FROM \`data\` WHERE \`content\`->'$.type'='user'");```
4. data table stores all posts', post-types' and users' data
5. trac table is for plugins to use as the developers deem fit, it can be used for web analytics data, to store user sessions or any other developer-defined use-case directly using mysql class available as $sql

### Documentation
1. all documentation of the project, client references, presentations, design files etc. to be maintained in /theme/docs/ folder
2. tribe documentation is currently only available on this README.md file

### Assets and dependencies
1. for theme assets use /theme/assets/
2. for nodejs dependencies use /package.json and cli command ```npm install <package-name>```
3. for php dependencies use /composer.json and cli command ```composer update```
4. any dependency not available in npm and composer, add it to /theme/assets/ folder manually

### Applications in NodeJS
1. in tribe, any nodejs front-end framework is called an app
2. to add an ember app, create a sub-folder in /applications/, the sub-folder must have 'dist', 'assets', 'package.json' and 'node_modules', the 'dist' folder's index.html will be executed when you visit the url &lt;domain.tld&gt;/app/&lt;sub-folder&gt;
3. front-end apps must *not* use the package.json available in tribe's root folder
4. every app must have their own package.json
5. to add any other nodejs app based on vue or svelte or react, create a sub-folder in /applications/, then modify the '/app' location block in the nginx config file to make sure the application is executed when you visit the url &lt;domain.tld&gt;/app/&lt;sub-folder&gt;
6. install or update package.json using
```sudo npm install```
7. for accessing the json-api to our database from the front-end application, you'll need to add the api url in your app, for which host is the BASE_URL and namespace is 'api', basically the adapter must fetch json results from BASE_URL/api
8. get started quickly with emberjs on your local machine https://guides.emberjs.com/release/getting-started/quick-start/, create it in /applications/ folder
9. build the app for production before pushing it to the server, once built push 'dist', 'assets', 'package.json' and 'node_modules' to the server, visit https://cli.emberjs.com/release/basic-use/deploying/
10. other important ember tutorials:<br>
https://guides.emberjs.com/release/getting-started/anatomy-of-an-ember-app/<br>
https://guides.emberjs.com/release/models/<br>
https://guides.emberjs.com/release/models/customizing-adapters/

### NginX config
- nginx config is available at /etc/nginx/sites-available/&lt;domain.tld&gt;

### phpMyAdmin access
- URL to phpMyAdmin: &lt;domain.tld&gt;/vendor/wildfire/admin/plugins/phpmyadmin

## Dependencies built by Wildfire
packages built by us for tribe will be available in 3 ways:
1. composer package, available in /vendor/wildfire/ folder
2. npm package, available in /node_modules/ folder
3. as front-end apps that can be saved to /applications/ folder

## Uninstall
```
sudo bash -c "$(wget --no-cache --no-cookie https://raw.githubusercontent.com/wil-ldf-ire/tribe-uninstall/master/uninstall.sh -O -)"
```
more info on uninstall - https://github.com/wil-ldf-ire/tribe-uninstall

## Versioning and Release cycle management
- versioning is based on semvar - https://semver.org/
- we're starting to follow a 6-week release cycle, releasing a new version every 6th monday
- latest releases - https://github.com/wil-ldf-ire/tribe/releases
- we have 2 types of releases - beta and production

## More info and contact
https://wildfiretech.co/page/our-approach<br>
https://github.com/wil-ldf-ire

![Wildfire logo](https://wildfiretech.co/theme/assets/img/logo-bg.png "Wildfire logo")