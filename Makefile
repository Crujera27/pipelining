ADDLICENSE = $(shell go env GOPATH)/bin/addlicense

server:
	php -S 127.0.0.1:8000 -t .

deps:
	go install github.com/google/addlicense@latest

license:
	$(ADDLICENSE) -f HEADER.txt .

check:
	$(ADDLICENSE) -check -f HEADER.txt .