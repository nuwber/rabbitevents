#!/usr/bin/env bash

##############
# This is the full copy of Laravel Framework's scrypt.
# I hope this copying does not violate any rights
##############

set -e
set -x

if [ -z "$1" ]; then
    CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
else
    CURRENT_BRANCH=$1
fi

if [ "$(uname)" == "Darwin" ]; then
    SPLITSH_LITE="./bin/splitsh-lite"
elif [ "$(expr substr $(uname -s) 1 5)" == "Linux" ]; then
    SPLITSH_LITE="./bin/splitsh-lite-linux"
fi

function split()
{
    chmod +x $SPLITSH_LITE
    SHA1=`$SPLITSH_LITE --prefix=$1`
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
