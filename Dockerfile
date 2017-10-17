FROM node:8

# prepare build dir
RUN mkdir -p /app

# prepare limited user
RUN useradd --no-create-home --home-dir /nonexistent --shell /bin/false parsoid
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
