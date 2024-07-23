import os
import logging
from flask import Flask, request, jsonify, render_template
from PIL import Image
import base64
from io import BytesIO
from transformers import pipeline

app = Flask(__name__)
logging.basicConfig(level=logging.DEBUG)

classifier = pipeline(model="lxyuan/vit-xray-pneumonia-classification")

@app.route('/')
def home():
    return render_template('index.html')

@app.route('/predict', methods=['POST'])
def predict():
    if 'file' not in request.files:
        return jsonify({"error": "No file part in the request"}), 400

    file = request.files['file']

    if file.filename == '':
        return jsonify({"error": "No selected file"}), 400

    try:
        image = Image.open(file)
        if image.mode != 'RGB':
            image = image.convert('RGB')

        buffered = BytesIO()
        image.save(buffered, format="JPEG")
        img_bytes = buffered.getvalue()

        result = classifier(image)
        return render_template('index.html', prediction=result)
    except Exception as e:
        logging.error(f"Error occurred: {str(e)}")
        return jsonify({"error": str(e)}), 500

if __name__ == '__main__':
    app.run(debug=True)
