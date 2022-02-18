#!/usr/bin/env bash

##############
# This is the full copy of Laravel Framework's scrypt.
# I hope this copying does not violate any rights
##############

set -e
set -x

CURRENT_BRANCH="7.x"

function split()
{
    SHA1=`./bin/splitsh-lite --prefix=$1`
    git push $2 "$SHA1:refs/heads/$CURRENT_BRANCH" -f
}

function remote()
{
    git remote add $1 $2 || true
}

git pull origin $CURRENT_BRANCH

remote foundation git@github.com:rabbitevents/foundation.git
remote publisher git@github.com:rabbitevents/publisher.git
remote listener git@github.com:rabbitevents/listener.git

split 'src/RabbitEvents/Foundation' foundation
split 'src/RabbitEvents/Publisher' publisher
split 'src/RabbitEvents/Listener' listener
