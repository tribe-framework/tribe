installpath1=$(echo "install_path" | sed 's/\//\\\//g');

#--------CORE CHANGES BEGIN---------#

sudo chown ubuntu:ubuntu install_path/xyz.com -R;
sudo mkdir install_path/xyz.com/uploads;
sudo chown www-data:www-data install_path/xyz.com/uploads -R;

sudo cp install_path/xyz.com/install/nginx.conf /etc/nginx/sites-available/xyz.com;
sudo sed -i 's/your_server_ip/ipv4_address/g' /etc/nginx/sites-available/xyz.com;
sudo sed -i 's/your_server_base_dir/xyz.com/g' /etc/nginx/sites-available/xyz.com;
sudo sed -i 's/your_server_domain/xyz.com/g' /etc/nginx/sites-available/xyz.com;
sudo sed -i 's/xyz-port-var/xyz_port/g' /etc/nginx/sites-available/xyz.com;
sudo ln -s /etc/nginx/sites-available/xyz.com /etc/nginx/sites-enabled/xyz.com;
sudo cp install_path/xyz.com/install/apache2.conf /etc/apache2/sites-available/xyz.com.conf;
sudo sed -i 's/your_server_base_dir/xyz.com/g' /etc/apache2/sites-available/xyz.com.conf;
sudo sed -i 's/your_server_domain/xyz.com/g' /etc/apache2/sites-available/xyz.com.conf;
sudo sed -i 's/your_server_email/admin_email/g' /etc/apache2/sites-available/xyz.com.conf;
sudo sed -i "s/your_server_path/$installpath1/g" /etc/apache2/sites-available/xyz.com.conf;
a2ensite xyz.com;
#sudo certbot --agree-tos --no-eff-email --email admin_email --nginx -d xyz.com -d www.xyz.com;

if [ -v "$localport" ]
then
	sudo cp install_path/xyz.com/install/nginx.app.conf /etc/nginx/sites-available/app.xyz.com;
	sudo sed -i 's/your_server_ip/ipv4_address/g' /etc/nginx/sites-available/app.xyz.com;
	sudo sed -i 's/your_server_base_dir/xyz.com/g' /etc/nginx/sites-available/app.xyz.com;
	sudo sed -i 's/your_server_domain/app.xyz.com/g' /etc/nginx/sites-available/app.xyz.com;
	sudo sed -i 's/xyz-port-var/xyz_port/g' /etc/nginx/sites-available/app.xyz.com;
	sudo ln -s /etc/nginx/sites-available/app.xyz.com /etc/nginx/sites-enabled/app.xyz.com;
	sudo cp install_path/xyz.com/install/apache2.conf /etc/apache2/sites-available/app.xyz.com.conf;
	sudo sed -i 's/your_server_base_dir/xyz.com/g' /etc/apache2/sites-available/app.xyz.com.conf;
	sudo sed -i 's/your_server_domain/app.xyz.com/g' /etc/apache2/sites-available/app.xyz.com.conf;
	sudo sed -i 's/your_server_email/admin_email/g' /etc/apache2/sites-available/app.xyz.com.conf;
	sudo sed -i "s/your_server_path/$installpath1/g" /etc/apache2/sites-available/app.xyz.com.conf;
	a2ensite app.xyz.com;
	#sudo certbot --agree-tos --no-eff-email --email admin_email --nginx -d app.xyz.com -d www.app.xyz.com;
fi

sudo cp install_path/xyz.com/config/vars.php.sample install_path/xyz.com/config/vars.php;
sudo sed -i 's/xyz-domain-var/xyz.com/g' install_path/xyz.com/config/vars.php;
sudo sed -i 's/xyz-db-name-var/mysql_w_user/g' install_path/xyz.com/config/vars.php;
sudo sed -i 's/xyz-db-pass-var/mysql_w_pass/g' install_path/xyz.com/config/vars.php;
sudo sed -i "s/xyz-install-path/$installpath1/g" install_path/xyz.com/config/vars.php;
sudo sed -i 's/xyz-db-pass-var/mysql_w_pass/g' install_path/xyz.com/install/install.php;
sudo sed -i 's/your_server_email/admin_email/g' install_path/xyz.com/install/install.php;
sudo sed -i "s/xyz-install-path/$installpath1/g" install_path/xyz.com/install/install.php;
sudo sed -i 's/xyz-domain-var/xyz.com/g' install_path/xyz.com/install/install.php;
echo "CREATE USER 'mysql_w_user'@'localhost' IDENTIFIED WITH mysql_native_password BY 'mysql_w_pass'; FLUSH PRIVILEGES;" | mysql -umysql_root_user -pmysql_root_pass -hlocalhost;
echo "CREATE DATABASE mysql_w_user CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;" | mysql -umysql_root_user -pmysql_root_pass -hlocalhost;
echo "GRANT ALL PRIVILEGES on mysql_w_user.* to 'mysql_w_user'@'localhost';" | mysql -umysql_root_user -pmysql_root_pass -hlocalhost;
sudo mysql -umysql_w_user -pmysql_w_pass mysql_w_user < install_path/xyz.com/install/install.sql;
sudo bash install/composer.sh;
php composer.phar install;
php composer.phar dump-autoload;
sudo php install_path/xyz.com/install/install.php;
sudo rm install_path/xyz.com/install -R;
sudo rm install_path/xyz.com/config/*.sample;

sudo /bin/dd if=/dev/zero of=/var/swap.1 bs=1M count=1024;
sudo /sbin/mkswap /var/swap.1;
sudo /sbin/swapon /var/swap.1;

sudo service apache2 restart;
sudo service nginx restart;

#--------CORE CHANGES END---------#