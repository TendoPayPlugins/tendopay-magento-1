all: clean test

test:
	tendo_vendor/bin/phpunit

coverage:
	tendo_vendor/bin/phpunit --coverage-html=artifacts/coverage

view-coverage:
	open artifacts/coverage/index.html

clean:
	rm -rf artifacts/*
