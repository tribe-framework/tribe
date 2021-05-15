# Tribe - a web project management framework by wildfire.

## Install instructions
```
sudo bash -c "$(wget --no-cache --no-cookie https://raw.githubusercontent.com/wil-ldf-ire/tribe/master/install/install.sh -O -)"
```

## Update instructions
```
sudo composer update
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

## Advanced instructions

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
7. assets are things that developers add themselves to the app, like images and fonts. dependencies are resources that come from third-party libraries. more on assets and dependencies in section below.

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
6. user interface for member users can be modified by including php files in theme folder with these names: user-header.php, user-footer.php, user-index.php, user-dashboard.php and so on. file name references from github.com/wil-ldf-ire/auth repository.

### Tribe utilities
1. most important functions from core features are $dash->push_content(array) and $dash->get_content($id)
2. when using $dash->push_content, the array passed needs to have 'type' and 'content_privacy'. if 'id' is available in the array, it modifies the exiting row
3. have a look at github.com/wil-ldf-ire/core for more details on available classes
4. visit /admin in the browser - a backend CMS called junction, log in using email address and database password defined in /.env file, visit github.com/wil-ldf-ire/admin for more info about it's codebase
5. our user authentication code is available at github.com/wil-ldf-ire/auth
6. /logs/ folder gives access to nginx access and error logs, these files cannot be accessed by the public, need root access

### Theme
1. within /theme folder, pages/index.php is the file that loads on the root URL. pages/\_header.php, pages/\_footer.php are what they sound like. you can create more files like \_nav.php, \_blog.php etc, based on your requirements.
2. includes/\_init.php makes wildfire/core functions available to the theme.
3. functions.php offers a Functions class that can be used for theme-specific use.

### Database structure
1. there are 2 tables - data and trac. both tables have same table structure
2. tribe uses json heavily, the need for creating new tables is eleminated by use of json
3. the column 'content' uses json, example usage:
```$sql->executeSQL("SELECT \`id\` FROM \`data\` WHERE \`content\`->'$.type'='user'");```
4. data table stores all posts', post-types' and users' data
5. trac table is for plugins to use as the developers deem fit, it can be used for web analytics data, to store user sessions or any other developer-defined use-case directly using mysql class available as $sql

### Project docs
all documentation of the project, client references, presentations, design files etc. to be maintained in docs/ folder of the theme.

### Assets and dependencies
1. theme assets are available at /theme/assets/
2. any dependency available with composer should use /composer.json
3. any dependency not available in composer, like a theme, plugin etc. to be added to /theme/assets/ folder manually.

### Applications in NodeJS
1. in tribe, any nodejs front-end framework is called an app
2. to add an ember app, create a sub-folder in /applications/, the sub-folder must have 'dist', 'assets', 'package.json' and 'node_modules', the 'dist' folder's index.html will be executed when you visit the url &lt;domain.tld&gt;/app/&lt;sub-folder&gt;
3. to add any other nodejs app based on vue or svelte or react, create a sub-folder in /applications/, then modify the '/app' location block in the nginx config file to make sure the application is executed when you visit the url &lt;domain.tld&gt;/app/&lt;sub-folder&gt;
4. install or update package.json using
```sudo npm install```
5. for accessing the json-api to our database from the front-end application, you'll need to add the api url in your app, for which host is the BASE_URL and namespace is 'api', basically the adaptor must fetch json results from BASE_URL/api
5. we highly recommend emberjs, to know more visit emberjs.com

### NginX config
1. nginx config is available at /etc/nginx/sites-available/&lt;domain.tld&gt;

### phpMyAdmin access
URL to phpMyAdmin: &lt;domain.tld&gt;/vendor/wildfire/admin/plugins/phpmyadmin

### More info and contact
wildfiretech.co/page/our-approach
github.com/wil-ldf-ire