FROM node:10.16.0-alpine

RUN apk add --no-cache git python make g++

# prepare build dir
RUN mkdir -p /app

WORKDIR /app

# install npm packages
COPY package.json /app
RUN npm install --production

FROM node:10.16.0-alpine

EXPOSE 8080

# copy the sources and run the server
COPY --from=0 /app /app
COPY . /app

WORKDIR /app

# prepare limited user
RUN adduser -SH -g '' -h /nonexistent parsoid
RUN chown -R parsoid /app

# do not run as root
USER parsoid

ENV ENV "dev"

CMD [ "npm", "start" ]
