CURRENT_DIR := $(shell pwd)
DOCKER_IMAGE := artifactory.wikia-inc.com/services/parsoid-redux

docker_build:
	docker build -t ${DOCKER_IMAGE}:0.1.6 .
docker_upload:
	docker push ${DOCKER_IMAGE}:0.1.6
deploy:
	docker run -it --rm -v ${CURRENT_DIR}/k8s.yaml:/k8s_descriptor-poz-dev.yaml artifactory.wikia-inc.com/ops/k8s-deployer:0.0.9 kubectl apply -f /k8s_descriptor-poz-dev.yaml -n dev --context=kube-poz-dev
