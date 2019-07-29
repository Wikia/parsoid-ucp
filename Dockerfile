FROM node:10.16.0-alpine

# prepare build dir
RUN mkdir -p /app

# prepare limited user
RUN adduser -SH -g '' -h /nonexistent parsoid
RUN chown -R parsoid /app

WORKDIR /app

# install npm packages
COPY package.json /app
RUN npm install --production

EXPOSE 8080

# copy the sources and run the server
COPY . /app

# do not run as root
USER parsoid

ENV ENV "dev"

CMD [ "npm", "start" ]
