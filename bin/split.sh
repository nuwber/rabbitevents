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
    echo "Running splitsh: $SPLITSH_LITE --prefix=$1"
    
    # Debug: Check if binary runs
    $SPLITSH_LITE --help > /dev/null || echo "WARNING: splitsh-lite failed to run --help"

    SHA1=`$SPLITSH_LITE --prefix=$1`
    echo "Generated SHA1: $SHA1"

    if [ -z "$SHA1" ]; then
        echo "Error: splitsh-lite returned empty SHA1 for prefix $1"
        exit 1
    fi

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
