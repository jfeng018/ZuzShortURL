#!/bin/bash
printenv | grep -E '^(DATABASE_URL|ADMIN_TOKEN)' >> /etc/apache2/envvars

exec apache2-foreground