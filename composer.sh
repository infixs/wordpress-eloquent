#!/bin/bash

# Execute Composer using Docker

docker run --rm --interactive --tty --volume .:/app composer "$@"