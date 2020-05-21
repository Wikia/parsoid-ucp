FROM node:10.20.1-alpine

RUN apk add --no-cache git python make g++

# prepare build dir
RUN mkdir -p /app

WORKDIR /app

# install npm packages
COPY package.json package-lock.json /app/
RUN npm ci --production

FROM node:10.20.1-alpine

EXPOSE 8080

# copy the sources and run the server
COPY --from=0 /app /app
COPY . /app

WORKDIR /app

# prepare limited user
RUN chown -R 65534:65534 /app

# do not run as root
USER 65534

ENV ENV "dev"

CMD [ "npm", "start" ]
