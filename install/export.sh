#Initialising install path and IP address of host
installpath="/var/www/html";

#Initialising colours
red=`tput setaf 1`;
green=`tput setaf 2`;
reset=`tput sgr0`;

#user input for directory details
read -p "Website Domain: " websitedomain;
read -p "MySQL Root Username: " mysqluser;
echo "MySQL Root Password:";
read -s mysqlpass;
read -p "Database Name: " mysqlwuser;
echo "Database Password:";
read -s mysqlwpass;

#ARE YOU SURE prompt
#yes, no or cancel
read -rp "You're about to compress and export the folder, NginX config, Letsencrypt config and MySQL database of $websitedomain at location $installpath/$websitedomain/$websitedomain.tar. Are you sure you wish to proceed [y/n/c]: ";

#choosing cancelled in ARE YOU SURE prompt
[[ ${REPLY,,} =~ ^(c|cancel|C|CANCEL|Cancel)$ ]] && {
	echo "Website not exported. Action cancelled.";
	exit 1;
}

#choosing yes in ARE YOU SURE prompt
if [[ ${REPLY,,} =~ ^(y|yes|Y|YES|Yes)$ ]]; then

	if [ -f "$installpath/$websitedomain/$websitedomain.tar" ];
	then
		echo "${red}ERROR:${reset} A file already exists on the path $installpath/$websitedomain/$websitedomain.tar.";
	else
	
		if [ -n "$websitedomain" ] && [ -n "$mysqluser" ] && [ -n "$mysqlpass" ] && [ -n "$mysqlwuser" ] && [ -n "$mysqlwpass" ]
		then
			#replace placeholders with user inputs
			mkdir -p $installpath/$websitedomain/export/letsencrypt;
			sudo cp /etc/letsencrypt/live/$websitedomain $installpath/$websitedomain/export/letsencrypt/live/ -R;
			sudo cp /etc/letsencrypt/archive/$websitedomain $installpath/$websitedomain/export/letsencrypt/archive/ -R;
			sudo mysqldump -u root -p$mysqlpass $mysqlwuser > $installpath/$websitedomain/export/db.sql;
			sudo cp /etc/nginx/sites-available/$websitedomain $installpath/$websitedomain/export/nginx;
			sudo cp /etc/letsencrypt/options-ssl-nginx.conf $installpath/$websitedomain/export/;
			sudo cp /etc/letsencrypt/ssl-dhparams.pem $installpath/$websitedomain/export/;
			cd $installpath/;
			tar -cvf $installpath/$websitedomain/$websitedomain.tar $websitedomain;

			#display domain details
			echo -e "\r\n### -----------------\r\n";
			echo "${green}Website successfully exported.${reset}";
			echo -e "\r\n### -----------------\r\n";
		
		else
			echo "${red}ERROR:${reset} Website details not defined. All fields are compulsory. Please try again.";
		fi

	fi

#choosing no or invalid input in ARE YOU SURE prompt
else
	echo "Website not exported. Action cancelled.";
fi