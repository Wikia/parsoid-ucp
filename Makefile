CURRENT_DIR := $(shell pwd)
DOCKER_IMAGE := artifactory.wikia-inc.com/services/parsoid-redux
VERSION := 0.1.7

docker_build:
	docker build -t ${DOCKER_IMAGE}:0.1.7 .
docker_upload:
	docker push ${DOCKER_IMAGE}:0.1.7
deploy:
	docker run -it --rm -v ${CURRENT_DIR}/k8s.yaml:/k8s_descriptor-poz-dev.yaml artifactory.wikia-inc.com/ops/k8s-deployer:0.0.22 kubectl apply -f /k8s_descriptor-poz-dev.yaml -n dev --context=kube-poz-dev
