task :default => :run

desc 'Build site with Jekyll'
task :build do
  sh 'yarn --non-interactive --frozen-lockfile --no-progress --dev install'
  sh 'yarn run build'
  jekyll 'build'
end

desc 'Build site and start server with --auto'
task :run do
  sh 'yarn --non-interactive --frozen-lockfile --no-progress --dev install'
  sh 'yarn run build'
  jekyll 'serve --incremental --watch'
end

def jekyll(opts = '')
  sh 'rm -rf _site'
  sh 'bundle exec jekyll ' + opts
end
