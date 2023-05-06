import os, sys, re
import requests
import urllib.parse
import json
from urllib.parse import urljoin
from bs4 import BeautifulSoup

class Sneaker:
  def __init__(self, name, nick, imgurl, url, price):
    self.name = name
    self.nick = nick
    self.url = url
    self.imgurl = imgurl
    self.price = price


# ToDo: Clean up file handling maybe remove it since we dont need to save the files
def savePage(url, pagepath = 'page'):
    def savenRename(soup, pagefolder, session, url, tag, inner):
        if not os.path.exists(pagefolder):
            os.mkdir(pagefolder)
        for res in soup.findAll(tag): # images, css, etc..
            if res.has_attr(inner): # check inner tag (file object) MUST exists  
                try:
                    filename, ext = os.path.splitext(os.path.basename(res[inner])) # get name and extension
                    filename = re.sub('\W+', '', filename) + ext # clean special chars from name
                    fileurl = urljoin(url, res.get(inner))
                    filepath = os.path.join(pagefolder, filename)
                    # rename html ref so can move html and folder of files anywhere
                    res[inner] = os.path.join(os.path.basename(pagefolder), filename)
                    if not os.path.isfile(filepath): # was not downloaded
                        with open(filepath, 'wb') as file:
                            filebin = session.get(fileurl)
                            file.write(filebin.content)
                except Exception as exc:
                    print(exc, file = sys.stderr)
    
    # Webrequest
    # ToDo: Get additional pages of search results
    session = requests.Session()
    #... whatever other requests config you need here
    response = session.get(url)

    # Parse the JSON
    # ToDo: Stop saving files and reopening them add try catches
    soup = BeautifulSoup(response.text, "html.parser")
    item = soup.find('script', id='__NEXT_DATA__').text
    json_object = json.loads(item)
    with open('flightclub.json', 'w', encoding='utf-8') as f:
        json.dump(json_object, f, ensure_ascii=True, indent=4)

    with open('flightclub.json') as f:
        jobj = json.load(f)
    sres = (jobj['props']['pageProps']['resultsState']['rawResults'])[0]['hits']
    with open("searchresults.json", "w") as f:
        f.write(json.dumps(sres, ensure_ascii=True, indent=4))

    # HTML for the table
    html_template = """<html>
    <head>
    <title>Flight Club - (""" + fcterm + """)</title>

    </head>
    <body>
    <h2>Flight Club - (""" + fcterm + """)</h2>

    <table width='600px'>
    """  

    # ToDo: Finish sneaker class and use a class object 
    for res in sres:
        html_template += "<tr>"
        html_template += "<td><img src='" + res['main_picture_url'] + "' width='300px'></img></td>"
        html_template += "<td>" + res['name'] + " (" + res['nickname'] + ")</td>"
        html_template += "<td>$" + str(res['retail_price_cents_usd'])[:-2] + "</td>"
        html_template += "</tr>"
        pstr = res['main_picture_url'] + "\n" + res['name'] + "\n(" + res['nickname'] + ")\n$" + str(res['retail_price_cents_usd'])[:-2]
        print(pstr + "\n")

    html_template += """  
    </body>
    </html>
    """

    # to open/create a new html file in the write mode
    with open('fcout.html', 'w') as f:
    # writing the code into the file
        f.write(html_template)

    # nickname
    # size_range
    # gender
    # grid_picture_url
    # main_picture_url
    # retail_price_cents_usd

    path, _ = os.path.splitext(pagepath)
    pagefolder = path+'_files' # page contents folder
    tags_inner = {'img': 'src', 'link': 'href', 'script': 'src'} # tag&inner tags to grab
    for tag, inner in tags_inner.items(): # saves resource files and rename refs
        savenRename(soup, pagefolder, session, url, tag, inner)
    with open(path+'.html', 'wb') as file: # saves modified html doc
        file.write(soup.prettify('utf-8'))

# Search Flight Club by term
# ToDo: Add more options to the search like gender etc
fcterm = "retro 5"
fcenc = urllib.parse.quote(fcterm)
fcurl = "https://www.flightclub.com/catalogsearch/result?query=" + fcenc
fcpath = "flightclub" 
savePage(fcurl, fcpath)
