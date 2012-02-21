#!/usr/bin/env python


"""
	a simple webserver that waits for a post and does a git pull then
"""
import time
import cgi
import BaseHTTPServer
import json
import subprocess
import os

HOST_NAME = '0.0.0.0'
PORT_NUMBER = 9999
GIT_REPO = '/home/springtest/www'
LOG_FILE = '/home/springtest/webhook.log'

f = open(LOG_FILE, 'a')

def log(msg, s = {}):
	f.write("%s %s\n" % (time.asctime(), str(msg)))
	f.flush()

class MyHandler(BaseHTTPServer.BaseHTTPRequestHandler):
	def log_message(self, format,*args):
		log(self.client_address[0] +" "+format %( args))
	def do_HEAD(s):
		s.send_response(200)
		s.send_header("Content-type", "text/html")
		s.end_headers()
	def do_GET(s):
		"""Respond to a GET request."""
		s.send_response(200)
		s.send_header("Content-type", "text/html")
		s.end_headers()
		s.wfile.write("<html><head><title>Title goes here.</title></head>")
		s.wfile.write("<body><p>This is a test.</p>")
		# If someone went to "http://something.somewhere.net/foo/bar/",
		# then s.path equals "/foo/bar/".
		s.wfile.write("<p>You accessed path: %s</p>" % s.path)
		s.wfile.write("</body></html>")
	def do_POST(self):
		if self.path != "/github-webhook":
			self.log_message("Invalid path")
			return
		# Parse the form data posted
		form = cgi.FieldStorage(
			fp=self.rfile,
			headers=self.headers,
			environ={'REQUEST_METHOD':'POST',
				'CONTENT_TYPE':self.headers['Content-Type'],
			})

		# Begin the response
		self.send_response(200)
		self.end_headers()
		self.wfile.write('Path: %s\n' % self.path)
		self.wfile.write('Form data:\n')
		if not 'payload' in form:
			self.log_message("Invalid request")
			return
		data = json.loads(form['payload'].value)
		if 'repository' in data and 'name' in data['repository'] and data['repository']['name'] == 'spring-website':
			self.checkout(self)
		#else:
		#	dumpPost(form)
	def dumpPost(self, form):

		# Echo back information about what was posted in the form
		for field in form.keys():
			field_item = form[field]
			if field_item.filename:
				# The field contains an uploaded file
				file_data = field_item.file.read()
				file_len = len(file_data)
				del file_data
				self.wfile.write('\tUploaded %s (%d bytes)\n' % (field,
							 file_len))
			else:
				# Regular form value
				self.wfile.write('\t%s=%s\n' % (field, form[field].value))
				self.log_message('\t%s=%s\n' % (field, form[field].value))

	def checkout(self, s):
		""" update the git repository """
		origWD = os.getcwd()
		os.chdir(GIT_REPO)
		try:
			output = subprocess.check_output(["git", "pull"])
		except Exception as e:
			self.log_message("Exception: %s", str(e))
		os.chdir(origWD)
		self.log_message("git pull: " + output.replace("\n", ""))

if __name__ == '__main__':
	server_class = BaseHTTPServer.HTTPServer
	try:
		httpd = server_class((HOST_NAME, PORT_NUMBER), MyHandler)
		log("Server Starts - %s:%s" % (HOST_NAME, PORT_NUMBER))
		httpd.serve_forever()
	except KeyboardInterrupt:
		httpd.server_close()
		pass
	log("Server Stops - %s:%s" % (HOST_NAME, PORT_NUMBER))
	f.close

