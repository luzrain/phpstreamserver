#!/bin/bash

# Subtree split on tag this script gets called using find:
# find src -maxdepth 2 -name composer.json -print0 | xargs -I '{}' -n 1 -0 bash subtree.sh {} refs/tags/3.1.5
# find src -maxdepth 2 -name composer.json -print0 | xargs -I '{}' -n 1 -0 bash subtree.sh {} refs/heads/3.1
# See the subtree workflow
set -xe
package=$(jq -r .name $1)
directory=$(dirname $1)
repository="https://github.com/$package"
git remote add $package $repository
sha=$(splitsh-lite --prefix=$directory)
git push $package $sha:$2
