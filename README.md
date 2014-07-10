Rackban
==============

Introduction
--------------
Fail2Ban is a great tool to keep unwanted traffic away causing unnecessary load on your servers. For users behind Rackspace load balancers, the traffic is hard to filter as the incoming IP would be the internal IP of the load balancer.

This Fail2Ban action/script combo will manage bans via the Rackspace Load Balancer API, which stops unwanted traffic from reaching your network.

Setup
--------------
- Edit scripts/rackban.php to match your configuration
- Put the script in a safe area on your server(s) running Fail2Ban
- Move action.d/rackban.conf to your Fail2Ban actions directory (typically /etc/fail2ban/actions.d) and update the '/path/to' location for the PHP script
- Change your jail configuration to use the new action
- Do the happy dance!

Testing
--------------
Once you've completed step 1 above, you can simple run the following to test your configuration

    php /path/to/rackban.php ban 192.168.1.1
    
Make sure the IP doesn't match anything currently on your network!

A 'Failure!' response typically means the IP or command is wrong. An exception means your credentials are likely wrong.
