#Initialising install path and IP address of host
installpath="/var/www/html";

#Initialising colours
red=`tput setaf 1`;
green=`tput setaf 2`;
reset=`tput sgr0`;

#user input for directory details
read -p "Tar File URL: " zipurl;
read -p "Website Domain: " websitedomain;
read -p "MySQL Root Username: " mysqluser;
echo "MySQL Root Password:";
read -s mysqlpass;
read -p "Database Name: " mysqlwuser;
echo "Database Password:";
read -s mysqlwpass;

#ARE YOU SURE prompt
#yes, no or cancel
read -rp "You're about to de-compress and import the folder, NginX config, Letsencrypt config and MySQL database of $websitedomain at location $installpath/$websitedomain. Are you sure you wish to proceed [y/n/c]: ";

#choosing cancelled in ARE YOU SURE prompt
[[ ${REPLY,,} =~ ^(c|cancel|C|CANCEL|Cancel)$ ]] && {
	echo "Website not imported. Action cancelled.";
	exit 1;
}

#choosing yes in ARE YOU SURE prompt
if [[ ${REPLY,,} =~ ^(y|yes|Y|YES|Yes)$ ]]; then

	if [ -d "$installpath/$websitedomain" ];
	then
		echo "${red}ERROR:${reset} A folder already exists on the path $installpath/$websitedomain.";
	else
	
		if [ -n "$websitedomain" ] && [ -n "$mysqluser" ] && [ -n "$mysqlpass" ] && [ -n "$mysqlwuser" ] && [ -n "$mysqlwpass" ]
		then
			#replace placeholders with user inputs
			cd $installpath/;
			sudo wget --no-check-certificate $zipurl;
			sudo tar -xvf $websitedomain.tar;
			sudo chown root: $installpath/$websitedomain -R;
			sudo chown www-data: $installpath/$websitedomain/uploads -R;
			sudo cp $installpath/$websitedomain/export/nginx /etc/nginx/sites-available/$websitedomain;
			mysql -u root -p$mysqlpass -e "CREATE DATABASE $mysqlwuser;
			CREATE USER '$mysqlwuser'@'%' IDENTIFIED BY '$mysqlwpass';
			GRANT ALL PRIVILEGES ON $mysqlwuser.* TO '$mysqlwuser'@'%';";
			sudo mysql -u root -p$mysqlpass $mysqlwuser < $installpath/$websitedomain/export/db.sql;
			sudo cp -n $installpath/$websitedomain/export/options-ssl-nginx.conf /etc/letsencrypt/;
			sudo cp -n $installpath/$websitedomain/export/ssl-dhparams.pem /etc/letsencrypt/;
			sudo ln -s /etc/nginx/sites-available/$websitedomain /etc/nginx/sites-enabled/$websitedomain;
			sudo mkdir -p /etc/letsencrypt/live/$websitedomain;
			sudo mkdir -p /etc/letsencrypt/archive/$websitedomain;
			sudo cp $installpath/$websitedomain/export/letsencrypt/live/* /etc/letsencrypt/live/$websitedomain/ -R;
			sudo cp $installpath/$websitedomain/export/letsencrypt/archive/* /etc/letsencrypt/archive/$websitedomain/ -R;
			sudo service nginx restart;
			sudo rm $installpath/$websitedomain/export -R;
			sudo rm $installpath/$websitedomain.tar;

			#display domain details
			echo -e "\r\n### -----------------\r\n";
			echo "${green}Website successfully imported.${reset}";
			echo -e "\r\n### -----------------\r\n";
		
		else
			echo "${red}ERROR:${reset} Website details not defined. All fields are compulsory. Please try again.";
		fi

	fi

#choosing no or invalid input in ARE YOU SURE prompt
else
	echo "Website not imported. Action cancelled.";
fi