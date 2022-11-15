import groovy.json.JsonOutput

def namespace = "dev"
def targets = ["poz", "sjc"]
// DEV slack channel as a default
def slackChannel = "#services-deploy-dev"
def isDevEnv = true
def buildUser = "Anonymous"
def parsoidRolloutStatus

if (params.environment == "prod") {
    targets = ["sjc","res"]
    namespace = "prod"
    slackChannel = "#services-deploy"
    isDevEnv = false
}

def deployenv = 'artifactory.wikia-inc.com/platform/alpine:3.6-curl'
def kubectl = env["K8S_DEPLOYER_IMAGE"]

node('docker-daemon'){
    def serviceVersion = "0.0.0"
    def imageName = "artifactory.wikia-inc.com/services/unified-platform-parsoid"
    def imageExists = false

    stage('Clone sources'){
        wrap([$class: 'BuildUser']) {
            try {
                buildUser = "${BUILD_USER}"
            }
            catch (MissingPropertyException ex) {
                buildUser = "Jenkins"
            }
        }
        git url: 'https://github.com/Wikia/parsoid-ucp.git', branch: params.commit
    }

    stage('Check if image already exists'){
        serviceVersion = sh(script: "git rev-parse --short HEAD", returnStdout: true).trim()
        def status = sh(script: "curl -u ${env.JENKINS_ARTIFACTORY_USERNAME}:${env.JENKINS_ARTIFACTORY_PASSWORD}  -w \"%{http_code}\" -s -I -o /dev/null -XGET \"https://artifactory.wikia-inc.com/artifactory/api/storage/dockerv2-local/services/unified-platform-parsoid/${serviceVersion}\"", returnStdout: true).trim()
        if ( status == "200" ){
            println "Image ${imageName}:${serviceVersion} already exists"
            imageExists = true
        }
    }

    // If image exists, skip building an image and go straight to Kubernetes deploy
    if ( !imageExists ){
        stage('Push To Artifactory'){
            sh "docker build -t ${imageName}:${serviceVersion} . && docker push ${imageName}:${serviceVersion}"
        }
    }

    stage('Notify Slack Channel publish'){
        def publishMsg = "'unified-platform-parsoid' with ${serviceVersion} version is published by '${buildUser}'"

        slackSend(channel: slackChannel, message: publishMsg)
    }


    for (String target: targets) {
        def context = "kube-${target}-${params.environment}"
        def datacenter = isDevEnv ? "${target}-dev" : target
        imageName = "artifactory.wikia-inc.com/services/unified-platform-parsoid:${serviceVersion}"

        stage('Generate Descriptors'){
            def replicas = isDevEnv ? 1 : 2

            def parsoidConfiguration = readFile "k8s.yaml"

            writeFile file: "parsoid-apply.yaml", text: ((new groovy.text.SimpleTemplateEngine().createTemplate(parsoidConfiguration)).make([
                    'replicas':replicas,
                    'env':params.environment,
                    'imageName':imageName,
                    'datacenter': datacenter,
                    'severity': isDevEnv ? 'warning' : 'critical',
                    'send_pd': !isDevEnv ? 'send' : '""'
            ]).toString())
        }

        withDockerContainer(kubectl) {
            stage('Deploy to Kubernetes') {
                sh "kubectl -n ${namespace} --context ${context} --kubeconfig=/config/.kube/config apply -f parsoid-apply.yaml"
                parsoidRolloutStatus = sh(returnStatus: true, script: "kubectl --kubeconfig=/config/.kube/config --context '${context}' -n '${namespace}' rollout status deployment/unified-platform-parsoid")
            }
        }

        withDockerContainer(deployenv) {
            stage('Notify Slack Channel deploy') {
                def wasDeploymentSuccessful = parsoidRolloutStatus == 0
                def color = wasDeploymentSuccessful ? '#36a64f' : '#cc142c'
                def statusEmoji = wasDeploymentSuccessful ? ':checkmark:' : ':siren:'
                def status = wasDeploymentSuccessful ? 'success' : 'failed'
                def attachments = JsonOutput.toJson(
                        [
                                [
                                        pretext: "${statusEmoji} unified-platform-parsoid rollout status",
                                        color  : color,
                                        fields : [
                                                [title: "status", value: status, short: true],
                                                [title: "K8S cluster", value: context, short: true],
                                                [title: "namespace", value: namespace, short: true],
                                                [title: "image version", value: serviceVersion, short: true]
                                        ]
                                ]
                        ]
                )

                slackSend(channel: slackChannel, color: color, attachments: attachments)
            }
        }
    }
}
