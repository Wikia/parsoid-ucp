FROM artifactory.wikia-inc.com/dockerhub/node:16.13.0-alpine AS builder

RUN apk add --no-cache git python3 make g++

# prepare build dir
RUN mkdir -p /app

WORKDIR /app

# install npm packages
COPY package.json package-lock.json /app/
RUN npm ci --production

COPY . /app

FROM artifactory.wikia-inc.com/dockerhub/node:16.13.0-alpine

EXPOSE 8080

# copy the sources and run the server
COPY --from=builder --chown=65534:65534 /app /app

WORKDIR /app

# do not run as root
USER 65534

ENV ENV "dev"

CMD [ "npm", "start" ]
