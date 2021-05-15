#Initialising install path
installpath="/var/www/html";
installpath1=$(echo "$installpath" | sed 's/\//\\\//g');

#user input for directory details
read -p "Website Domain: " websitedomain;
read -p "MySQL Root Username: " mysqluser;
read -p "MySQL Root Password: " mysqlpass;
read -p "Website Database Name: " mysqlwuser;
read -p "Website Admin Email: " adminemail;
read -p "Website Password: " mysqlwpass;
read -p "IP Address: " ipv4address;

#get tribe from github
sudo git clone https://github.com/wil-ldf-ire/tribe.git $installpath/$websitedomain;

#remove .git folder
cd $installpath/$websitedomain;
sudo rm -rf .git;

#prepare website.sh for execution
#replace placeholders with user inputs in website.sh file
sudo sed -i "s/xyz.com/$websitedomain/g" $installpath/$websitedomain/install/website.sh;
sudo sed -i "s/ipv4_address/$ipv4address/g" $installpath/$websitedomain/install/website.sh;
sudo sed -i "s/mysql_root_user/$mysqluser/g" $installpath/$websitedomain/install/website.sh;
sudo sed -i "s/mysql_root_pass/$mysqlpass/g" $installpath/$websitedomain/install/website.sh;
sudo sed -i "s/admin_email/$adminemail/g" $installpath/$websitedomain/install/website.sh;
sudo sed -i "s/install_path/$installpath1/g" $installpath/$websitedomain/install/website.sh;
sudo sed -i "s/mysql_w_user/$mysqlwuser/g" $installpath/$websitedomain/install/website.sh;
sudo sed -i "s/mysql_w_pass/$mysqlwpass/g" $installpath/$websitedomain/install/website.sh;

#run website.sh
sudo bash $installpath/$websitedomain/install/website.sh;