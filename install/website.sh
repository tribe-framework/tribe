installpath1=$(echo "install_path" | sed 's/\//\\\//g');

#--------CORE CHANGES BEGIN---------#

sudo cp install_path/xyz.com/install/nginx.conf /etc/nginx/sites-available/xyz.com;
sudo sed -i 's/your_server_ip/ipv4_address/g' /etc/nginx/sites-available/xyz.com;
sudo sed -i 's/your_server_base_dir/xyz.com/g' /etc/nginx/sites-available/xyz.com;
sudo sed -i 's/your_server_domain/xyz.com/g' /etc/nginx/sites-available/xyz.com;
sudo sed -i 's/xyz-port-var/xyz_port/g' /etc/nginx/sites-available/xyz.com;
sudo ln -s /etc/nginx/sites-available/xyz.com /etc/nginx/sites-enabled/xyz.com;
sudo certbot --agree-tos --no-eff-email --email admin_email --nginx -d xyz.com -d www.xyz.com;
sudo sed -i 's/xyz-domain-var/xyz.com/g' install_path/xyz.com/package.json;

sudo cp install_path/xyz.com/.env.sample install_path/xyz.com/.env;
sudo sed -i 's/xyz-domain-var/xyz.com/g' install_path/xyz.com/.env;
sudo sed -i 's/xyz-db-name-var/mysql_w_user/g' install_path/xyz.com/.env;
sudo sed -i 's/xyz-db-pass-var/mysql_w_pass/g' install_path/xyz.com/.env;
sudo sed -i 's/xyz-port-var/xyz_port/g' install_path/xyz.com/.env;
sudo sed -i 's/your_server_email/admin_email/g' install_path/xyz.com/.env;
sudo sed -i "s/xyz-install-path/$installpath1/g" install_path/xyz.com/.env;
sudo sed -i 's/port_num/xyz_port/g' install_path/xyz.com/README.md;
echo "CREATE USER 'mysql_w_user'@'localhost' IDENTIFIED WITH mysql_native_password BY 'mysql_w_pass'; FLUSH PRIVILEGES;" | mysql -umysql_root_user -pmysql_root_pass -hlocalhost;
echo "CREATE DATABASE mysql_w_user CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;" | mysql -umysql_root_user -pmysql_root_pass -hlocalhost;
echo "GRANT ALL PRIVILEGES on mysql_w_user.* to 'mysql_w_user'@'localhost';" | mysql -umysql_root_user -pmysql_root_pass -hlocalhost;
sudo mysql -umysql_w_user -pmysql_w_pass mysql_w_user < install_path/xyz.com/install/install.sql;

sudo rm install_path/xyz.com/install -R;
sudo rm install_path/xyz.com/.env.sample;
sudo chown www-data: install_path/xyz.com/uploads -R;

composer update;
npm install bootstrap;
npm install popper;

sudo service nginx restart;

#--------CORE CHANGES END---------#